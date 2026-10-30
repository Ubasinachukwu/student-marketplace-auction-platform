<?php
// marketplace/lib/fpdf/receipt-template.php
// Shared FPDF builder for receipts and shipping labels.

require_once __DIR__ . '/fpdf.php';

class MarketplacePDF extends FPDF
{
    public $siteName;
    public $adminEmail;
    public $currency;
    public $useUnicodeFont;
    public $thumbnailDir;
    public $listingImagesDir;
    public $thumbnailsDir;

    public function __construct()
    {
        parent::__construct('P', 'mm', 'A4');

        $this->siteName   = defined('SITE_NAME')   ? SITE_NAME   : 'MarketPlace';
        $this->adminEmail = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : '';

        // Detect Unicode font
        $dejavuFile = __DIR__ . '/font/dejavusans.php';
        $this->useUnicodeFont = file_exists($dejavuFile);

        if ($this->useUnicodeFont) {
            $this->AddFont('DejaVu', '', 'dejavusans.php');
            $this->AddFont('DejaVu', 'B', 'dejavusansb.php');
            $this->currency = '₦';
        } else {
            $this->currency = 'NGN ';
        }

        // Image paths
        $this->listingImagesDir = defined('LISTING_IMAGES_PATH') 
            ? rtrim(LISTING_IMAGES_PATH, '/\\') . DIRECTORY_SEPARATOR 
            : __DIR__ . '/../../assets/uploads/listings/';

        $this->thumbnailsDir = $this->listingImagesDir . 'thumbnails' . DIRECTORY_SEPARATOR;

        if (!is_dir($this->thumbnailsDir)) {
            @mkdir($this->thumbnailsDir, 0777, true);
        }
    }

    public function setFontSafe($style = '', $size = 11)
    {
        if ($this->useUnicodeFont) {
            $this->SetFont('DejaVu', $style, $size);
        } else {
            $this->SetFont('Helvetica', $style, $size);
        }
    }

    public function money($amount)
    {
        return $this->currency . number_format((float)$amount, 2);
    }

    public function kvRow($label, $value)
    {
        $this->setFontSafe('B', 11);
        $this->Cell(60, 8, $label, 0, 0);
        $this->setFontSafe('', 11);
        $this->Cell(0, 8, $value, 0, 1);
    }

    public function hr()
    {
        $this->SetDrawColor(200, 200, 200);
        $this->Line($this->GetX(), $this->GetY(), $this->GetX() + 190, $this->GetY());
        $this->Ln(3);
    }

    public function siteHeader($title)
    {
        $this->setFontSafe('B', 20);
        $this->SetTextColor(40, 40, 40);
        $this->Cell(0, 10, $this->siteName, 0, 1, 'C');

        $this->setFontSafe('', 12);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 6, $title, 0, 1, 'C');

        $this->SetTextColor(0, 0, 0);
        $this->Ln(4);
    }

    public function siteFooter()
    {
        $this->SetY(-20);
        $this->setFontSafe('I', 9);
        $this->SetTextColor(150, 150, 150);
        $this->Cell(0, 5, 'Thank you for shopping with ' . $this->siteName, 0, 1, 'C');
        if ($this->adminEmail) {
            $this->Cell(0, 5, 'Support: ' . $this->adminEmail, 0, 1, 'C');
        }
    }

    // =============================================
    // IMAGE HANDLING (GD-powered)
    // =============================================

    /**
     * Prepare an image for embedding in the PDF.
     * Returns the absolute filesystem path to a JPG ready for FPDF, or null.
     */
    public function prepareImage($filename, $maxWidth = 600)
    {
        if (empty($filename)) return null;

        $original = $this->listingImagesDir . basename($filename);
        if (!file_exists($original)) return null;

        // Thumbnail cache path
        $thumbName = 'thumb_' . $maxWidth . '_' . pathinfo($filename, PATHINFO_FILENAME) . '.jpg';
        $thumbPath = $this->thumbnailsDir . $thumbName;

        // Use cached thumbnail if fresh
        if (file_exists($thumbPath) && filemtime($thumbPath) >= filemtime($original)) {
            return $thumbPath;
        }

        // If GD not available, return original (may fail for WebP)
        if (!extension_loaded('gd')) {
            return $this->isFpdfSupported($original) ? $original : null;
        }

        try {
            $info = @getimagesize($original);
            if (!$info) return null;

            $mime = $info['mime'];
            $src = null;

            switch ($mime) {
                case 'image/jpeg':
                case 'image/jpg':
                    $src = @imagecreatefromjpeg($original);
                    break;
                case 'image/png':
                    $src = @imagecreatefrompng($original);
                    break;
                case 'image/gif':
                    $src = @imagecreatefromgif($original);
                    break;
                case 'image/webp':
                    if (function_exists('imagecreatefromwebp')) {
                        $src = @imagecreatefromwebp($original);
                    }
                    break;
                default:
                    return null;
            }

            if (!$src) return null;

            $origW = imagesx($src);
            $origH = imagesy($src);

            // Scale down if wider than max
            if ($origW > $maxWidth) {
                $newW = $maxWidth;
                $newH = (int)round($origH * ($maxWidth / $origW));
                $scaled = imagescale($src, $newW, $newH);
                if ($scaled) {
                    imagedestroy($src);
                    $src = $scaled;
                }
            }

            // Write as JPG
            imagejpeg($src, $thumbPath, 82);
            imagedestroy($src);

            if (file_exists($thumbPath)) {
                return $thumbPath;
            }
        } catch (Exception $e) {
            error_log('PDF image prep failed: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Check if FPDF can embed this file directly (fallback when GD missing).
     */
    private function isFpdfSupported($path)
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return in_array($ext, ['jpg', 'jpeg', 'png'], true);
    }

    /**
     * Add an item image to the current row (receipt style).
     * Draws image on the left. Returns the width consumed (0 if no image).
     */
    public function addItemImage($filename, $size = 35)
    {
        $imgPath = $this->prepareImage($filename);

        if (!$imgPath) {
            return 0;
        }

        try {
            $x = $this->GetX();
            $y = $this->GetY();

            // Draw image with a light border
            $this->Image($imgPath, $x, $y, $size, $size);

            $this->SetDrawColor(220, 220, 220);
            $this->Rect($x, $y, $size, $size);

            // Move X past the image
            $this->SetXY($x + $size + 3, $y);

            return $size + 3;
        } catch (Exception $e) {
            error_log('PDF image embed failed: ' . $e->getMessage());
            return 0;
        }
    }
}