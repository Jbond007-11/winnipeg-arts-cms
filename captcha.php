<?php
require_once 'config.php';

class CaptchaGenerator {
    private $width = 180;
    private $height = 60;
    private $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    private $length = 5;
    
    public function generateCaptcha() {
        // Clean up old CAPTCHA sessions
        $this->cleanupOldSessions();
        
        // generate random string
        $captchaText = '';
        for ($i = 0; $i < $this->length; $i++) {
            $captchaText .= $this->characters[rand(0, strlen($this->characters) - 1)];
        }
        
        // Generate unique session ID
        $sessionId = md5(uniqid() . microtime());
        
        // Store in database
        $expiresAt = date('Y-m-d H:i:s', time() + 900); // 15 minutes
        global $pdo;
        $stmt = $pdo->prepare("INSERT INTO captcha_sessions (id, captcha_text, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$sessionId, $captchaText, $expiresAt]);
        
        // Store session ID in user session
        $_SESSION['captcha_id'] = $sessionId;
        
        return $captchaText;
    }
    
    public function createImage($text) {
        // Create image
        $image = imagecreate($this->width, $this->height);
        
        // Colors
        $bgColor = imagecolorallocate($image, 245, 245, 245);
        $textColors = [
            imagecolorallocate($image, 50, 50, 150),
            imagecolorallocate($image, 150, 50, 50),
            imagecolorallocate($image, 50, 150, 50),
            imagecolorallocate($image, 150, 100, 50)
        ];
        $lineColor = imagecolorallocate($image, 200, 200, 200);
        
        // Fill background
        imagefill($image, 0, 0, $bgColor);
        
        // Add noise lines
        for ($i = 0; $i < 6; $i++) {
            imageline($image, 
                rand(0, $this->width), rand(0, $this->height),
                rand(0, $this->width), rand(0, $this->height),
                $lineColor
            );
        }
        
        // Add text
        $fontSize = 5;
        $letterSpacing = $this->width / ($this->length + 1);
        
        for ($i = 0; $i < strlen($text); $i++) {
            $x = $letterSpacing * ($i + 1) - 10 + rand(-5, 5);
            $y = ($this->height / 2) - 10 + rand(-5, 5);
            $angle = rand(-15, 15);
            $color = $textColors[array_rand($textColors)];
            
            if (function_exists('imagettftext') && file_exists('fonts/arial.ttf')) {
                // Use TTF font if available
                imagettftext($image, 16, $angle, $x, $y + 15, $color, 'fonts/arial.ttf', $text[$i]);
            } else {
                // Fallback to built-in fonts
                imagestring($image, $fontSize, $x, $y, $text[$i], $color);
            }
        }
        
        // Add noise dots
        for ($i = 0; $i < 100; $i++) {
            imagesetpixel($image, rand(0, $this->width), rand(0, $this->height), 
                         imagecolorallocate($image, rand(200, 255), rand(200, 255), rand(200, 255)));
        }
        
        // Output image
        header('Content-Type: image/png');
        header('Cache-Control: no-cache');
        imagepng($image);
        imagedestroy($image);
    }
    
    public function verifyCaptcha($userInput) {
        if (!isset($_SESSION['captcha_id'])) {
            return false;
        }
        
        global $pdo;
        $stmt = $pdo->prepare("SELECT captcha_text FROM captcha_sessions WHERE id = ? AND (expires_at IS NULL OR expires_at > NOW())");
        $stmt->execute([$_SESSION['captcha_id']]);
        $result = $stmt->fetch();
        
        if (!$result) {
            return false;
        }
        
        // Clean up used CAPTCHA
        $stmt = $pdo->prepare("DELETE FROM captcha_sessions WHERE id = ?");
        $stmt->execute([$_SESSION['captcha_id']]);
        unset($_SESSION['captcha_id']);
        
        return strtoupper($userInput) === strtoupper($result['captcha_text']);
    }
    
    private function cleanupOldSessions() {
        global $pdo;
        $stmt = $pdo->prepare("DELETE FROM captcha_sessions WHERE expires_at IS NOT NULL AND expires_at < NOW()");
        $stmt->execute();
    }
}

// Handle CAPTCHA generation
if (isset($_GET['generate'])) {
    $captcha = new CaptchaGenerator();
    $text = $captcha->generateCaptcha();
    $captcha->createImage($text);
    exit;
}

// Handle CAPTCHA verification (AJAX)
if (isset($_POST['verify_captcha'])) {
    header('Content-Type: application/json');
    $captcha = new CaptchaGenerator();
    $isValid = $captcha->verifyCaptcha($_POST['captcha_input'] ?? '');
    echo json_encode(['valid' => $isValid]);
    exit;
}
?>