<?php
/**
 * Word to PDF Converter
 * Uses LibreOffice headless mode to convert .doc/.docx files to PDF.
 * All in one file: index.php
 * 
 * Requirements:
 * - PHP 7.4+ with exec() enabled
 * - LibreOffice installed (headless mode)
 * - 'temp' directory writable
 * 
 * Configure LIBREOFFICE_PATH below if needed.
 */

// ============================================================
// CONFIGURATION
// ============================================================
define('MAX_FILE_SIZE', 50 * 1024 * 1024);          // 50 MB
define('UPLOAD_DIR', __DIR__ . '/temp/');            // Temporary directory
define('LIBREOFFICE_PATH', 'libreoffice');           // Change to full path if needed, e.g. '/usr/bin/libreoffice'
define('ALLOWED_EXTENSIONS', ['doc', 'docx']);
define('ALLOWED_MIME_TYPES', [
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
]);

// ============================================================
// ERROR HANDLING & OUTPUT BUFFERING
// ============================================================
error_reporting(E_ALL);
ini_set('display_errors', 0); // Do not display errors to output
set_time_limit(0);            // Allow long conversions
ob_start();                   // Buffer output to prevent accidental headers

// ============================================================
// CREATE TEMP DIRECTORY IF MISSING
// ============================================================
if (!is_dir(UPLOAD_DIR)) {
    if (!mkdir(UPLOAD_DIR, 0755, true)) {
        // We'll handle later
    }
}

// ============================================================
// FIX: Force LibreOffice to use a writable profile directory
// ============================================================
putenv('HOME=/tmp');
$profileDir = '/tmp/libreoffice-profile';
if (!is_dir($profileDir)) {
    mkdir($profileDir, 0700, true);
}

// ============================================================
// AJAX HANDLER
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $response = handleUpload($_FILES['file']);
    ob_end_clean(); // Clear buffer before output

    if (isset($response['error'])) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $response['error']]);
        exit;
    }

    if (isset($response['pdf_path'])) {
        // Serve the PDF file for download
        $pdfPath = $response['pdf_path'];
        $wordPath = $response['word_path'];

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($pdfPath) . '"');
        header('Content-Length: ' . filesize($pdfPath));

        // Output the file and clean up
        readfile($pdfPath);
        unlink($pdfPath);
        unlink($wordPath);
        exit;
    }

    // Fallback error
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unknown error occurred.']);
    exit;
}

// ============================================================
// MAIN FUNCTION: HANDLE UPLOAD AND CONVERSION (FIXED)
// ============================================================
function handleUpload($file)
{
    // 1. Validate upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'Upload failed with error code: ' . $file['error']];
    }

    // 2. Check size
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['error' => 'File exceeds the maximum allowed size of 50 MB.'];
    }

    // 3. Validate extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS)) {
        return ['error' => 'Invalid file extension. Allowed: .doc, .docx'];
    }

    // 4. Validate MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, ALLOWED_MIME_TYPES)) {
        return ['error' => 'Invalid file MIME type. Allowed: application/msword, application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    }

    // 5. Generate safe random names
    $randomName = bin2hex(random_bytes(16));
    $wordFilename = $randomName . '.' . $ext;
    $wordPath = UPLOAD_DIR . $wordFilename;

    // 6. Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $wordPath)) {
        return ['error' => 'Failed to move uploaded file to temp directory.'];
    }

    // 7. Prepare conversion
    $pdfFilename = $randomName . '.pdf';
    $pdfPath = UPLOAD_DIR . $pdfFilename;

    // ---- FIX: Ensure LibreOffice profile exists and HOME is set ----
    // (already set globally, but we repeat for safety)
    putenv('HOME=/tmp');
    $profileDir = '/tmp/libreoffice-profile';
    if (!is_dir($profileDir)) {
        mkdir($profileDir, 0700, true);
    }

    // Build command with all recommended headless flags and explicit UserInstallation
    $cmd = escapeshellcmd(LIBREOFFICE_PATH)
        . ' --headless --nologo --nodefault --nolockcheck --nofirststartwizard'
        . ' -env:UserInstallation=file://' . $profileDir
        . ' --convert-to pdf --outdir ' . escapeshellarg(UPLOAD_DIR)
        . ' ' . escapeshellarg($wordPath);

    // 8. Execute conversion, capturing both stdout and stderr
    exec($cmd . ' 2>&1', $output, $returnCode);

    // 9. Check result
    if ($returnCode !== 0 || !file_exists($pdfPath) || filesize($pdfPath) === 0) {
        // Clean up word file
        if (file_exists($wordPath)) unlink($wordPath);
        // Return detailed error
        $errorMsg = 'Conversion failed. LibreOffice returned code ' . $returnCode . '.';
        if (!empty($output)) {
            $errorMsg .= ' Output: ' . implode("\n", $output);
        }
        return ['error' => $errorMsg];
    }

    // 10. Success: return paths for download and cleanup
    return [
        'word_path' => $wordPath,
        'pdf_path'  => $pdfPath
    ];
}

// ============================================================
// FRONTEND HTML (with integrated Navbar & Footer)
// ============================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Word to PDF Converter | ProToolss | WWW.PROTOOLSS.ONLINE</title>
    <meta name="description" content="Convert Word documents (.doc, .docx) to PDF instantly using this free, secure, and production-ready converter.">
    <meta name="keywords" content="word to pdf, doc to pdf, docx to pdf, free converter, online converter">

    <!-- ===== FAVICON ===== -->
    <link rel="icon" type="image/png" href="https://i.ibb.co/FkJPMZ8r/Chat-GPT-Image-Jun-18-2026-07-58-22-AM.png" />
    <link rel="shortcut icon" href="https://i.ibb.co/FkJPMZ8r/Chat-GPT-Image-Jun-18-2026-07-58-22-AM.png" />

    <!-- ===== FONTS & ICONS ===== -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        /* =============================================================
           GLOBAL RESET & BASE
           ============================================================= */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(145deg, #f0f4f8 0%, #d9e2ec 100%);
            color: #1a1a1a;
            line-height: 1.6;
            padding-top: 80px;          /* space for fixed navbar */
            padding-bottom: 70px;       /* space for fixed footer */
            min-height: 100vh;
        }

        /* Main content wrapper – no extra flex needed now because footer is fixed */
        .main-content {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px 20px 0;
            min-height: calc(100vh - 80px - 70px); /* viewport minus navbar and footer */
        }

        /* =============================================================
           NAVIGATION (fixed, with hamburger on right)
           ============================================================= */
        nav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 999;
            padding: 14px 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.04);
            box-shadow: 0 1px 20px rgba(0, 0, 0, 0.03);
            transition: all 0.4s cubic-bezier(0.2, 0.9, 0.3, 1.2);
        }

        nav.scrolled {
            padding: 10px 0;
            background: rgba(255, 255, 255, 0.98);
            box-shadow: 0 2px 30px rgba(0, 0, 0, 0.06);
        }

        .nav-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 20px;
            position: relative;
            flex-wrap: nowrap;
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
            min-width: 0;
            justify-content: space-between;
        }

        /* Hamburger - always visible, on the right */
        .hamburger {
            display: block !important;
            font-size: 1.6rem;
            color: #333;
            cursor: pointer;
            transition: all 0.3s ease;
            padding: 6px 8px;
            background: transparent;
            border: none;
            border-radius: 8px;
            line-height: 1;
            flex-shrink: 0;
            order: 2;
            margin-left: auto;
        }

        .hamburger:hover {
            color: #cc0000;
            background: rgba(204, 0, 0, 0.06);
        }

        /* Logo: "Pro" red, "Toolss" dark blue */
        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: -0.3px;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.3s ease;
            white-space: nowrap;
            flex-shrink: 1;
            min-width: 0;
            margin-right: auto;
        }

        .logo .pro {
            color: #cc0000;
        }
        .logo .toolss {
            color: #1a2a6c;
        }
        .logo-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            object-fit: cover;
            flex-shrink: 0;
        }

        /* No desktop nav links – hidden */
        .nav-center {
            display: none !important;
        }
        .nav-links {
            display: none !important;
        }

        /* Mobile menu – only Home link */
        .nav-links-mobile {
            display: none;
            flex-direction: column;
            width: 100%;
            gap: 0.6rem;
            padding: 16px 20px 12px;
            border-top: 1px solid rgba(0, 0, 0, 0.06);
            margin-top: 10px;
            background: rgba(255, 255, 255, 0.98);
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
            border-radius: 0 0 16px 16px;
        }
        .nav-links-mobile.show {
            display: flex;
        }
        .nav-links-mobile a {
            padding: 8px 0;
            font-size: 0.95rem;
            width: 100%;
            border-bottom: 1px solid rgba(0, 0, 0, 0.03);
            white-space: normal;
            color: #444;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: 0.3s;
        }
        .nav-links-mobile a:last-child {
            border-bottom: none;
        }
        .nav-links-mobile a i {
            width: 24px;
            text-align: center;
            flex-shrink: 0;
            color: #cc0000;
        }
        .nav-links-mobile a.active {
            color: #cc0000;
        }
        .nav-links-mobile a:hover {
            color: #cc0000;
            padding-left: 8px;
        }

        /* =============================================================
           CONVERTER UI
           ============================================================= */
        .converter-wrapper {
            background: #ffffff;
            border-radius: 32px;
            box-shadow: 0 20px 60px rgba(0, 20, 40, 0.15);
            padding: 40px 32px;
            max-width: 680px;
            width: 100%;
            transition: all 0.2s;
            margin: 0 auto 20px;
        }

        .converter-header {
            text-align: center;
            margin-bottom: 28px;
        }
        .converter-header h1 {
            font-size: 28px;
            font-weight: 700;
            color: #0a2e4a;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .converter-header h1 i {
            color: #2a7de1;
            font-size: 30px;
        }
        .converter-header p {
            color: #4a6a85;
            font-size: 15px;
            margin-top: 6px;
        }

        .drop-zone {
            border: 2px dashed #b8cbd9;
            border-radius: 20px;
            padding: 40px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #f8fafc;
            position: relative;
        }
        .drop-zone.dragover {
            border-color: #2a7de1;
            background: #eaf3ff;
            transform: scale(1.01);
        }
        .drop-zone i {
            font-size: 48px;
            color: #7a9bcb;
            margin-bottom: 12px;
        }
        .drop-zone .zone-title {
            font-size: 18px;
            font-weight: 600;
            color: #0a2e4a;
        }
        .drop-zone .zone-sub {
            font-size: 14px;
            color: #5a7a95;
            margin-top: 6px;
        }
        .drop-zone .file-name {
            margin-top: 14px;
            font-size: 15px;
            font-weight: 500;
            color: #1a4a6a;
            background: #e2edf7;
            padding: 8px 16px;
            border-radius: 30px;
            display: inline-block;
        }
        .drop-zone input[type="file"] {
            display: none;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 24px;
            justify-content: center;
        }
        .btn {
            padding: 12px 28px;
            border-radius: 50px;
            border: none;
            font-weight: 600;
            font-size: 16px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            transition: all 0.25s ease;
            background: #eef2f6;
            color: #1a3a5a;
        }
        .btn-primary {
            background: #2a7de1;
            color: white;
            box-shadow: 0 4px 12px rgba(42, 125, 225, 0.3);
        }
        .btn-primary:hover:not(:disabled) {
            background: #1b5fb0;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(42, 125, 225, 0.4);
        }
        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        .btn-outline {
            background: transparent;
            border: 2px solid #b8cbd9;
            color: #2a4a6a;
        }
        .btn-outline:hover {
            border-color: #2a7de1;
            background: #f0f6ff;
        }
        .btn i {
            font-size: 18px;
        }

        .progress-wrapper {
            margin-top: 20px;
            display: none;
        }
        .progress-wrapper.active {
            display: block;
        }
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 6px;
        }
        .progress-bar .fill {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, #2a7de1, #6aa3f0);
            border-radius: 10px;
            transition: width 0.2s linear;
        }
        .progress-status {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            color: #2a4a6a;
        }
        .progress-status i {
            margin-right: 8px;
        }

        .message {
            margin-top: 18px;
            padding: 14px 18px;
            border-radius: 14px;
            font-weight: 500;
            display: none;
            align-items: center;
            gap: 12px;
            font-size: 15px;
        }
        .message.success {
            display: flex;
            background: #e2f3e4;
            color: #1a6a3a;
            border: 1px solid #b2dfb8;
        }
        .message.error {
            display: flex;
            background: #fce8e8;
            color: #a13131;
            border: 1px solid #f5c2c2;
        }
        .message i {
            font-size: 22px;
        }

        /* =============================================================
           FOOTER – fixed at bottom (always visible)
           ============================================================= */
        footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 100;
            padding: 12px 0;
            background: linear-gradient(145deg, #1a1a2e, #16213e);
            color: #e0e0e0;
            text-align: center;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }
        .footer-content {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 20px;
        }
        .footer-powered {
            font-size: 1rem;
            color: #aaaaaa;
            font-weight: 300;
            letter-spacing: 0.5px;
        }
        .footer-powered a {
            color: #ff6b6b;
            text-decoration: none;
            font-weight: 600;
            transition: 0.3s;
        }
        .footer-powered a:hover {
            color: #ff4444;
            text-decoration: underline;
        }
        .footer-powered i {
            color: #ff6b6b;
            margin: 0 6px;
        }

        /* =============================================================
           RESPONSIVE TWEAKS
           ============================================================= */
        @media (max-width: 768px) {
            body {
                padding-top: 68px;
                padding-bottom: 60px;
            }
            nav {
                padding: 10px 0;
            }
            .nav-container {
                padding: 0 14px;
            }
            .nav-left {
                gap: 8px;
            }
            .hamburger {
                font-size: 1.5rem;
                padding: 4px 6px;
            }
            .logo {
                font-size: 1.2rem;
                gap: 6px;
            }
            .logo-icon {
                width: 28px;
                height: 28px;
            }
            .nav-links-mobile {
                padding: 12px 16px 10px;
                gap: 0.4rem;
            }
            .nav-links-mobile a {
                font-size: 0.85rem;
                padding: 6px 0;
            }
            .nav-links-mobile a i {
                width: 20px;
                font-size: 0.8rem;
            }
            .converter-wrapper {
                padding: 30px 20px;
                margin-bottom: 16px;
            }
            footer {
                padding: 10px 0;
            }
            .footer-powered {
                font-size: 0.9rem;
            }
            .main-content {
                min-height: calc(100vh - 68px - 60px);
                padding: 16px 16px 0;
            }
        }

        @media (max-width: 480px) {
            body {
                padding-top: 62px;
                padding-bottom: 55px;
            }
            nav {
                padding: 8px 0;
            }
            .nav-container {
                padding: 0 12px;
            }
            .nav-left {
                gap: 6px;
            }
            .hamburger {
                font-size: 1.3rem;
                padding: 3px 5px;
            }
            .logo {
                font-size: 1rem;
                gap: 4px;
            }
            .logo-icon {
                width: 24px;
                height: 24px;
            }
            .converter-wrapper {
                padding: 20px 16px;
                margin-bottom: 12px;
            }
            .converter-header h1 {
                font-size: 22px;
            }
            .drop-zone {
                padding: 28px 12px;
            }
            .btn {
                padding: 10px 20px;
                font-size: 14px;
                flex: 1 1 auto;
            }
            footer {
                padding: 8px 0;
            }
            .footer-powered {
                font-size: 0.8rem;
            }
            .main-content {
                min-height: calc(100vh - 62px - 55px);
                padding: 12px 12px 0;
            }
        }

        @media (max-width: 360px) {
            .logo {
                font-size: 0.85rem;
                gap: 3px;
            }
            .logo-icon {
                width: 20px;
                height: 20px;
            }
            .hamburger {
                font-size: 1.1rem;
                padding: 2px 4px;
            }
            .nav-container {
                padding: 0 8px;
            }
        }

        /* Desktop size – hamburger still on right */
        @media (min-width: 769px) {
            .nav-left {
                flex: 1;
                justify-content: space-between;
            }
            .hamburger {
                display: block !important;
                font-size: 1.8rem;
                padding: 8px 12px;
            }
            .nav-links-mobile {
                max-width: 300px;
                right: 0;
                left: auto;
                border-radius: 0 0 16px 16px;
            }
            .main-content {
                min-height: calc(100vh - 80px - 70px);
            }
        }
    </style>
</head>

<body>

    <!-- ===== NAVBAR ===== -->
    <nav id="mainNav">
        <div class="nav-container">
            <div class="nav-left">
                <a href="index.php" class="logo">
                    <img src="https://i.ibb.co/FkJPMZ8r/Chat-GPT-Image-Jun-18-2026-07-58-22-AM.png" alt="Pro Toolss Logo" class="logo-icon" />
                    <span class="pro">Pro</span><span class="toolss">Toolss</span>
                </a>
                <button class="hamburger" id="hamburgerBtn" aria-label="Toggle menu">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
            <!-- No desktop links -->
            <div class="nav-center" style="display:none !important;"></div>

            <!-- Mobile menu – only Home -->
            <div class="nav-links-mobile" id="navLinksMobile">
                <a href="index.php" class="active"><i class="fas fa-home"></i> Home</a>
            </div>
        </div>
    </nav>

    <!-- ===== MAIN CONTENT (Converter UI) ===== -->
    <div class="main-content">
        <div class="converter-wrapper">
            <div class="converter-header">
                <h1><i class="fas fa-file-pdf"></i> Word → PDF</h1>
                <p>Drop your .doc or .docx file and get a PDF instantly</p>
            </div>

            <!-- Upload Zone -->
            <div class="drop-zone" id="dropZone">
                <i class="fas fa-cloud-upload-alt"></i>
                <div class="zone-title">Drag & drop your file here</div>
                <div class="zone-sub">or click to browse</div>
                <div id="fileNameDisplay" class="file-name" style="display:none;"></div>
                <input type="file" id="fileInput" accept=".doc,.docx,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
            </div>

            <!-- Buttons -->
            <div class="actions">
                <button class="btn btn-outline" id="browseBtn"><i class="fas fa-folder-open"></i> Browse</button>
                <button class="btn btn-primary" id="convertBtn" disabled><i class="fas fa-exchange-alt"></i> Convert</button>
            </div>

            <!-- Progress -->
            <div class="progress-wrapper" id="progressWrapper">
                <div class="progress-status">
                    <span><i class="fas fa-spinner fa-pulse"></i> Converting...</span>
                    <span id="progressPercent">0%</span>
                </div>
                <div class="progress-bar">
                    <div class="fill" id="progressFill"></div>
                </div>
            </div>

            <!-- Messages -->
            <div id="message" class="message"></div>
        </div>
    </div>

    <!-- ===== FOOTER (fixed at bottom) ===== -->
    <footer>
        <div class="footer-content">
            <div class="footer-powered">
                <i class=""></i> Powered by
                <a href="https://www.protoolss.online" target="_blank" rel="noopener noreferrer">www.protoolss.online</a>
                <i class="" style="color: #ff6b6b;"></i>
            </div>
        </div>
    </footer>

    <!-- =============================================================
         JAVASCRIPT – Navbar & Converter
         ============================================================= -->
    <script>
        (function() {
            // ===== NAVBAR SCROLL EFFECT =====
            const nav = document.getElementById('mainNav');
            window.addEventListener('scroll', function() {
                if (window.scrollY > 50) nav.classList.add('scrolled');
                else nav.classList.remove('scrolled');
            });

            // ===== HAMBURGER TOGGLE =====
            const hamburger = document.getElementById('hamburgerBtn');
            const navLinksMobile = document.getElementById('navLinksMobile');

            hamburger.addEventListener('click', function(e) {
                e.stopPropagation();
                navLinksMobile.classList.toggle('show');
                const icon = this.querySelector('i');
                icon.classList.toggle('fa-bars');
                icon.classList.toggle('fa-times');
            });

            // Close mobile menu on link click
            document.querySelectorAll('#navLinksMobile a').forEach(link => {
                link.addEventListener('click', function() {
                    navLinksMobile.classList.remove('show');
                    const icon = hamburger.querySelector('i');
                    icon.classList.add('fa-bars');
                    icon.classList.remove('fa-times');
                });
            });

            // Close mobile menu on outside click
            document.addEventListener('click', function(e) {
                if (!e.target.closest('nav') && navLinksMobile.classList.contains('show')) {
                    navLinksMobile.classList.remove('show');
                    const icon = hamburger.querySelector('i');
                    icon.classList.add('fa-bars');
                    icon.classList.remove('fa-times');
                }
            });

            // =============================================================
            // CONVERTER LOGIC (unchanged)
            // =============================================================
            const dropZone = document.getElementById('dropZone');
            const fileInput = document.getElementById('fileInput');
            const browseBtn = document.getElementById('browseBtn');
            const convertBtn = document.getElementById('convertBtn');
            const fileNameDisplay = document.getElementById('fileNameDisplay');
            const progressWrapper = document.getElementById('progressWrapper');
            const progressFill = document.getElementById('progressFill');
            const progressPercent = document.getElementById('progressPercent');
            const messageEl = document.getElementById('message');

            let selectedFile = null;
            let isConverting = false;

            function selectFile(file) {
                if (!file) return;
                const allowedExt = ['doc', 'docx'];
                const ext = file.name.split('.').pop().toLowerCase();
                if (!allowedExt.includes(ext)) {
                    showMessage('error', 'Please select a .doc or .docx file.');
                    return;
                }
                if (file.size > 50 * 1024 * 1024) {
                    showMessage('error', 'File exceeds 50 MB limit.');
                    return;
                }
                selectedFile = file;
                fileNameDisplay.textContent = file.name + ' (' + (file.size / 1024 / 1024).toFixed(2) + ' MB)';
                fileNameDisplay.style.display = 'inline-block';
                convertBtn.disabled = false;
                hideMessage();
                progressWrapper.classList.remove('active');
                progressFill.style.width = '0%';
                progressPercent.textContent = '0%';
            }

            function triggerFileInput() {
                fileInput.click();
            }

            function startConversion() {
                if (!selectedFile || isConverting) return;

                isConverting = true;
                convertBtn.disabled = true;
                hideMessage();
                progressWrapper.classList.add('active');
                progressFill.style.width = '0%';
                progressPercent.textContent = '0%';

                let progress = 0;
                const progressInterval = setInterval(() => {
                    if (progress < 90) {
                        progress += Math.random() * 8 + 2;
                        if (progress > 90) progress = 90;
                        progressFill.style.width = progress + '%';
                        progressPercent.textContent = Math.round(progress) + '%';
                    }
                }, 300);

                const formData = new FormData();
                formData.append('file', selectedFile);

                fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        clearInterval(progressInterval);
                        const contentType = response.headers.get('content-type') || '';
                        if (contentType.includes('application/pdf')) {
                            return response.blob().then(blob => ({
                                success: true,
                                blob: blob,
                                filename: getFilenameFromHeaders(response) || 'converted.pdf'
                            }));
                        } else {
                            return response.json().then(data => ({
                                success: false,
                                error: data.error || 'Conversion failed.'
                            }));
                        }
                    })
                    .then(result => {
                        progressFill.style.width = '100%';
                        progressPercent.textContent = '100%';

                        if (result.success) {
                            const url = URL.createObjectURL(result.blob);
                            const a = document.createElement('a');
                            a.href = url;
                            a.download = result.filename;
                            document.body.appendChild(a);
                            a.click();
                            document.body.removeChild(a);
                            setTimeout(() => URL.revokeObjectURL(url), 5000);
                            showMessage('success', '✅ Conversion successful! PDF downloaded automatically.');
                        } else {
                            showMessage('error', '❌ ' + result.error);
                        }
                    })
                    .catch(err => {
                        clearInterval(progressInterval);
                        showMessage('error', '❌ Network error: ' + err.message);
                    })
                    .finally(() => {
                        isConverting = false;
                        convertBtn.disabled = (selectedFile === null);
                        progressWrapper.classList.remove('active');
                        setTimeout(() => {
                            progressFill.style.width = '0%';
                            progressPercent.textContent = '0%';
                        }, 1500);
                    });
            }

            function getFilenameFromHeaders(response) {
                const disposition = response.headers.get('content-disposition');
                if (disposition) {
                    const match = disposition.match(/filename="(.+?)"/);
                    if (match) return match[1];
                }
                return null;
            }

            function showMessage(type, text) {
                messageEl.className = 'message ' + type;
                messageEl.innerHTML = text;
                messageEl.style.display = 'flex';
            }

            function hideMessage() {
                messageEl.style.display = 'none';
                messageEl.className = 'message';
            }

            // Event listeners
            browseBtn.addEventListener('click', triggerFileInput);
            fileInput.addEventListener('change', function(e) {
                if (this.files.length > 0) {
                    selectFile(this.files[0]);
                }
                this.value = '';
            });

            dropZone.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.classList.add('dragover');
            });
            dropZone.addEventListener('dragleave', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
            });
            dropZone.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
                if (e.dataTransfer.files.length > 0) {
                    selectFile(e.dataTransfer.files[0]);
                }
            });
            dropZone.addEventListener('click', function(e) {
                if (e.target === this || e.target.closest('.drop-zone') === this) {
                    triggerFileInput();
                }
            });

            convertBtn.addEventListener('click', startConversion);
            convertBtn.disabled = true;
        })();
    </script>

</body>
</html>
