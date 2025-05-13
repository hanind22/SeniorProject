<?php
require_once 'libs/phpqrcode/qrlib.php'; // Use this path if phpqrcode is inside fyp/libs

// Generate a simple QR code
QRcode::png('Hello from QR test');
