FROM php:8.3-apache

RUN apt-get update && \
    apt-get install -y \
    libreoffice \
    libreoffice-writer \
    fonts-dejavu \
    fonts-liberation \
    qpdf && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

COPY . /var/www/html/

RUN mkdir -p /var/www/html/uploads \
    /var/www/html/output && \
    chmod -R 777 /var/www/html/uploads \
    /var/www/html/output

EXPOSE 80
