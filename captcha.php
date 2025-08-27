<?php
require_once 'config.php';

class CaptchaGenerator {
    private $width = 180;
    private $height = 60;
    private $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    private $length = 5;
    
    public function generateCaptcha() {
        $this->cleanupOldSessions();
        
        $captchaText = '';
        for ($i = 0; $i < $this->length; $i++) {
            $captchaText .= $this->characters[rand(0, strlen($this->characters) - 1)];
        }
        
        $sessionId = md5(uniqid() . microtime());
        
        $expiresAt = date('Y-m-d H:i:s', time() + 900);
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("INSERT INTO captcha_sessions (id, captcha_text, expires_at) VALUES (?, ?, ?)");
            $stmt->execute([$sessionId, $captchaText, $expiresAt]);
            
            $_SESSION['captcha_id'] = $sessionId;
            
            return $captchaText;
        } catch (PDOException $e) {
            error_log("CAPTCHA database error: " . $e->getMessage());
            return $this->generateSimpleCaptcha();
        }
    }
    
    private function generateSimpleCaptcha() {
        $captchaText = '';
        for ($i = 0; $i < $this->length; $i++) {
            $captchaText .= $this->characters[rand(0, strlen($this->characters) - 1)];
        }
        $_SESSION['simple_captcha'] = $captchaText;
        return $captchaText;
    }
    
    public function createImage($text) {
        if (!extension_loaded('gd')) {
            header('Content-Type: text/plain');
            echo $text;
            return;
        }
        
        $image = imagecreate($this->width, $this->height);
        
        $bgColor = imagecolorallocate($image, 245, 245, 245);
        $textColors = [
            imagecolorallocate($image, 50, 50, 150),
            imagecolorallocate($image, 150, 50, 50),
            imagecolorallocate($image, 50, 150, 50),
            imagecolorallocate($image, 150, 100, 50)
        ];
        $lineColor = imagecolorallocate($image, 200, 200, 200);
        
        imagefill($image, 0, 0, $bgColor);
        
        for ($i = 0; $i < 6; $i++) {
            imageline($image, 
                rand(0, $this->width), rand(0, $this->height),
                rand(0, $this->width), rand(0, $this->height),
                $lineColor
            );
        }
        
        $fontSize = 5;
        $letterSpacing = $this->width / ($this->length + 1);
        
        for ($i = 0; $i < strlen($text); $i++) {
            $x = $letterSpacing * ($i + 1) - 10 + rand(-5, 5);
            $y = ($this->height / 2) - 10 + rand(-5, 5);
            $color = $textColors[array_rand($textColors)];
            
            imagestring($image, $fontSize, $x, $y, $text[$i], $color);
        }
        
        for ($i = 0; $i < 100; $i++) {
            imagesetpixel($image, rand(0, $this->width), rand(0, $this->height), 
                         imagecolorallocate($image, rand(200, 255), rand(200, 255), rand(200, 255)));
        }
        
        header('Content-Type: image/png');
        header('Cache-Control: no-cache');
        imagepng($image);
        imagedestroy($image);
    }
    
    public function verifyCaptcha($userInput) {
        if (empty($userInput)) {
            return false;
        }
        
        if (isset($_SESSION['simple_captcha'])) {
            $result = strtoupper($userInput) === strtoupper($_SESSION['simple_captcha']);
            unset($_SESSION['simple_captcha']);
            return $result;
        }
        
        if (!isset($_SESSION['captcha_id'])) {
            return false;
        }
        
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT captcha_text FROM captcha_sessions WHERE id = ? AND (expires_at IS NULL OR expires_at > NOW())");
            $stmt->execute([$_SESSION['captcha_id']]);
            $result = $stmt->fetch();
            
            if (!$result) {
                return false;
            }
            
            $stmt = $pdo->prepare("DELETE FROM captcha_sessions WHERE id = ?");
            $stmt->execute([$_SESSION['captcha_id']]);
            unset($_SESSION['captcha_id']);
            
            return strtoupper($userInput) === strtoupper($result['captcha_text']);
        } catch (PDOException $e) {
            error_log("CAPTCHA verification error: " . $e->getMessage());
            return false;
        }
    }
    
    private function cleanupOldSessions() {
        global $pdo;
        try {
            $stmt = $pdo->prepare("DELETE FROM captcha_sessions WHERE expires_at IS NOT NULL AND expires_at < NOW()");
            $stmt->execute();
        } catch (PDOException $e) {
            error_log("CAPTCHA cleanup error: " . $e->getMessage());
        }
    }
}

if (isset($_GET['generate'])) {
    $captcha = new CaptchaGenerator();
    $text = $captcha->generateCaptcha();
    $captcha->createImage($text);
    exit;
}

if (isset($_POST['verify_captcha'])) {
    header('Content-Type: application/json');
    $captcha = new CaptchaGenerator();
    $isValid = $captcha->verifyCaptcha($_POST['captcha_input'] ?? '');
    echo json_encode(['valid' => $isValid]);
    exit;
}
?>