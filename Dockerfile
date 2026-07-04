# Install LibreOffice
RUN apt-get update && apt-get install -y \
    libreoffice \
    libreoffice-writer \
    && rm -rf /var/lib/apt/lists/*

# Set HOME environment variable for PHP and LibreOffice
ENV HOME=/tmp

# Create the profile directory and set permissions
RUN mkdir -p /tmp/libreoffice-profile && chmod 700 /tmp/libreoffice-profile

# Create the upload temp directory
RUN mkdir -p /app/temp && chmod 755 /app/temp
