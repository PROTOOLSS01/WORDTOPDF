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
// FRONTEND HTML (unchanged)
// ============================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Word to PDF Converter | Free Online</title>
    <meta name="description" content="Convert Word documents (.doc, .docx) to PDF instantly using this free, secure, and production-ready converter.">
    <meta name="keywords" content="word to pdf, doc to pdf, docx to pdf, free converter, online converter">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📄</text></svg>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* Reset and base */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        body {
            min-height: 100vh;
            background: linear-gradient(145deg, #f0f4f8 0%, #d9e2ec 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            background: #ffffff;
            border-radius: 32px;
            box-shadow: 0 20px 60px rgba(0, 20, 40, 0.15);
            padding: 40px 32px;
            max-width: 680px;
            width: 100%;
            transition: all 0.2s;
        }

        .header {
            text-align: center;
            margin-bottom: 28px;
        }

        .header h1 {
            font-size: 28px;
            font-weight: 700;
            color: #0a2e4a;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .header h1 i {
            color: #2a7de1;
            font-size: 30px;
        }

        .header p {
            color: #4a6a85;
            font-size: 15px;
            margin-top: 6px;
        }

        /* Upload area */
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

        /* Buttons row */
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

        /* Progress */
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

        /* Messages */
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

        /* Responsive */
        @media (max-width: 600px) {
            .container {
                padding: 24px 16px;
            }
            .header h1 {
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
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
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

<script>
    (function() {
        // DOM elements
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

        // ----- File selection logic -----
        function selectFile(file) {
            if (!file) return;
            // Validate extension (browser-side)
            const allowedExt = ['doc', 'docx'];
            const ext = file.name.split('.').pop().toLowerCase();
            if (!allowedExt.includes(ext)) {
                showMessage('error', 'Please select a .doc or .docx file.');
                return;
            }
            // Validate size (browser-side)
            if (file.size > 50 * 1024 * 1024) {
                showMessage('error', 'File exceeds 50 MB limit.');
                return;
            }

            selectedFile = file;
            fileNameDisplay.textContent = file.name + ' (' + (file.size / 1024 / 1024).toFixed(2) + ' MB)';
            fileNameDisplay.style.display = 'inline-block';
            convertBtn.disabled = false;
            hideMessage();
            // Reset previous progress
            progressWrapper.classList.remove('active');
            progressFill.style.width = '0%';
            progressPercent.textContent = '0%';
        }

        // Trigger file input
        function triggerFileInput() {
            fileInput.click();
        }

        // ----- Upload & conversion -----
        function startConversion() {
            if (!selectedFile || isConverting) return;

            isConverting = true;
            convertBtn.disabled = true;
            hideMessage();
            progressWrapper.classList.add('active');
            progressFill.style.width = '0%';
            progressPercent.textContent = '0%';

            // Simulate progress (for UX) while waiting for server
            let progress = 0;
            const progressInterval = setInterval(() => {
                // Simulate up to 90%
                if (progress < 90) {
                    progress += Math.random() * 8 + 2;
                    if (progress > 90) progress = 90;
                    progressFill.style.width = progress + '%';
                    progressPercent.textContent = Math.round(progress) + '%';
                }
            }, 300);

            // Prepare FormData
            const formData = new FormData();
            formData.append('file', selectedFile);

            // Use fetch with XHR-like progress (fetch doesn't support upload progress)
            // We'll just use fetch and handle response
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                clearInterval(progressInterval);
                // Check content-type to decide if it's PDF or error JSON
                const contentType = response.headers.get('content-type') || '';
                if (contentType.includes('application/pdf')) {
                    // Success: PDF binary
                    return response.blob().then(blob => ({
                        success: true,
                        blob: blob,
                        filename: getFilenameFromHeaders(response) || 'converted.pdf'
                    }));
                } else {
                    // Error: JSON
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
                    // Auto-download PDF
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
                // Reset progress bar after a moment
                setTimeout(() => {
                    progressFill.style.width = '0%';
                    progressPercent.textContent = '0%';
                }, 1500);
                // Clear selected file if needed? Keep it to allow re-conversion.
                // We don't clear the file, so user can convert again with same file.
            });
        }

        // Helper to extract filename from Content-Disposition header
        function getFilenameFromHeaders(response) {
            const disposition = response.headers.get('content-disposition');
            if (disposition) {
                const match = disposition.match(/filename="(.+?)"/);
                if (match) return match[1];
            }
            return null;
        }

        // ----- UI helpers -----
        function showMessage(type, text) {
            messageEl.className = 'message ' + type;
            messageEl.innerHTML = text;
            messageEl.style.display = 'flex';
        }

        function hideMessage() {
            messageEl.style.display = 'none';
            messageEl.className = 'message';
        }

        // ----- Event listeners -----
        // Browse button
        browseBtn.addEventListener('click', triggerFileInput);

        // File input change
        fileInput.addEventListener('change', function(e) {
            if (this.files.length > 0) {
                selectFile(this.files[0]);
            }
            // Reset input so same file can be selected again
            this.value = '';
        });

        // Drag and drop
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

        // Click on drop zone opens file dialog
        dropZone.addEventListener('click', function(e) {
            // Prevent if user clicks on inner elements that may trigger twice
            if (e.target === this || e.target.closest('.drop-zone') === this) {
                triggerFileInput();
            }
        });

        // Convert button
        convertBtn.addEventListener('click', startConversion);

        // Also allow enter key on drop zone?
        // Not needed

        // Initial state: no file selected
        convertBtn.disabled = true;
    })();
</script>

</body>
</html>
