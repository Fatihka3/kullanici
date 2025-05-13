<?php
// Hata mesajlarını başlangıçta boş tutalım
$error_message = '';
$success_message = '';

// Token ve type bilgilerini URL'den alıyoruz
$token = isset($_GET['token']) ? $_GET['token'] : null;
$token_hash = isset($_GET['token_hash']) ? $_GET['token_hash'] : null;
$recovery_token = $token ?? $token_hash ?? null;
$type = isset($_GET['type']) ? $_GET['type'] : null;

// API bilgileri
$supabase_url = 'https://tlyirvhkpvvvuwauqjvj.supabase.co';
$supabase_anon_key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InRseWlydmhrcHZ2dnV3YXVxanZqIiwicm9sZSI6ImFub24iLCJpYXQiOjE2ODc4NzQzOTMsImV4cCI6MjAwMzQ1MDM5M30.F6iBdZ2MVLwqvCvKE-XZr0jID6brBgYpqnU_rBcBGt4';

// Form gönderildiğinde işlem yap
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    $form_token = isset($_POST['token']) ? $_POST['token'] : '';
    
    // Şifre doğrulama
    if (empty($new_password) || strlen($new_password) < 6) {
        $error_message = 'Şifre en az 6 karakter olmalıdır.';
    } elseif ($new_password !== $confirm_password) {
        $error_message = 'Şifreler eşleşmiyor.';
    } elseif (empty($form_token)) {
        $error_message = 'Token bilgisi bulunamadı.';
    } else {
        // Supabase Admin REST API kullanarak şifre güncelleme
        try {
            // 1. Önce token ile oturum doğrulama
            $verify_url = "$supabase_url/auth/v1/verify";
            $verify_data = json_encode([
                'type' => 'recovery',
                'token' => $form_token,
            ]);
            
            $ch = curl_init($verify_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $verify_data);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'apikey: ' . $supabase_anon_key,
            ]);
            
            $response = curl_exec($ch);
            $verify_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $response_data = json_decode($response, true);
            
            // 2. Token doğrulandıysa şifre güncelle
            if ($verify_status >= 200 && $verify_status < 300 && isset($response_data['access_token'])) {
                $access_token = $response_data['access_token'];
                
                $update_url = "$supabase_url/auth/v1/user";
                $update_data = json_encode([
                    'password' => $new_password
                ]);
                
                $ch = curl_init($update_url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
                curl_setopt($ch, CURLOPT_POSTFIELDS, $update_data);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'apikey: ' . $supabase_anon_key,
                    'Authorization: Bearer ' . $access_token,
                ]);
                
                $update_response = curl_exec($ch);
                $update_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($update_status >= 200 && $update_status < 300) {
                    $success_message = 'Şifreniz başarıyla güncellendi. Uygulamaya dönebilirsiniz.';
                    
                    // 3 saniye sonra uygulamaya yönlendir
                    header("refresh:3;url=io.mobilapp.raphscans://login?reset=success");
                } else {
                    $error_data = json_decode($update_response, true);
                    $error_message = 'Şifre güncellenirken bir sorun oluştu: ' . ($error_data['message'] ?? 'Bilinmeyen hata');
                }
            } else {
                $error_data = json_decode($response, true);
                $error_message = 'Token doğrulanamadı: ' . ($error_data['message'] ?? 'Bilinmeyen hata');
            }
        } catch (Exception $e) {
            $error_message = 'Bir hata oluştu: ' . $e->getMessage();
        }
    }
}

// Token yoksa kullanıcıya uyarı gösterelim
$token_valid = !empty($recovery_token) && $type === 'recovery';
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>RaphScans Şifre Sıfırlama</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body { font-family: Arial, sans-serif; text-align: center; padding: 40px 20px; background-color: #f9f9f9; }
    .container { max-width: 500px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    .logo { margin-bottom: 20px; }
    h1 { color: #4F46E5; margin-bottom: 20px; }
    .message { margin: 20px 0; color: #555; }
    .app-button { background-color: #4F46E5; color: white; padding: 15px 30px; 
      border: none; border-radius: 8px; font-size: 16px; cursor: pointer; transition: all 0.2s; }
    .app-button:hover { background-color: #3730a3; transform: translateY(-2px); }
    .error { color: #e53e3e; margin: 20px 0; padding: 10px; background: #fee2e2; border-radius: 6px; }
    .success { color: #16a34a; margin: 20px 0; padding: 10px; background: #dcfce7; border-radius: 6px; }
    #debug { margin-top: 30px; font-size: 12px; color: #999; }
    .form-group { margin-bottom: 20px; text-align: left; }
    label { display: block; margin-bottom: 5px; color: #555; }
    input[type="password"] { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 16px; }
    .loading { margin: 20px 0; }
    .spinner { display: inline-block; width: 20px; height: 20px; border: 3px solid #f3f3f3; border-top: 3px solid #4F46E5; border-radius: 50%; animation: spin 1s linear infinite; margin-right: 10px; }
    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
  </style>
</head>
<body>
  <div class="container">
    <div class="logo">
      <img src="https://via.placeholder.com/150x50/4F46E5/FFFFFF?text=RaphScans" alt="RaphScans Logo">
    </div>
    <h1>Şifre Sıfırlama</h1>
    
    <?php if ($success_message): ?>
      <div class="success"><?php echo htmlspecialchars($success_message); ?></div>
      <p class="message">Uygulamaya yönlendiriliyorsunuz...</p>
      <div class="loading">
        <div class="spinner"></div> Yönlendiriliyor...
      </div>
    <?php elseif (!$token_valid): ?>
      <div class="error">Geçerli bir şifre sıfırlama bağlantısı bulunamadı. Lütfen e-postadaki bağlantıyı doğru şekilde kullandığınızdan emin olun.</div>
      <p class="message">Sorununuz devam ederse, lütfen uygulamadan tekrar şifre sıfırlama talebinde bulunun.</p>
    <?php else: ?>
      <?php if ($error_message): ?>
        <div class="error"><?php echo htmlspecialchars($error_message); ?></div>
      <?php endif; ?>
      
      <p class="message">Lütfen yeni şifrenizi girin:</p>
      
      <form method="post" action="">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($recovery_token); ?>">
        
        <div class="form-group">
          <label for="password">Yeni Şifre</label>
          <input type="password" id="password" name="password" placeholder="En az 6 karakter" required>
        </div>
        
        <div class="form-group">
          <label for="confirm_password">Şifre Tekrar</label>
          <input type="password" id="confirm_password" name="confirm_password" placeholder="Şifrenizi tekrar girin" required>
        </div>
        
        <button type="submit" class="app-button">Şifremi Güncelle</button>
      </form>
    <?php endif; ?>
    
    <?php
    // Debug bilgileri (geliştirme sırasında kullanışlı)
    if (isset($_GET['debug']) && $_GET['debug'] == 1):
    ?>
    <div id="debug">
      <h4>Debug Bilgileri (Sadece Test İçin)</h4>
      <p>Token: <?php echo $token ? substr($token, 0, 10) . '...' : 'Yok'; ?></p>
      <p>Token Hash: <?php echo $token_hash ? substr($token_hash, 0, 10) . '...' : 'Yok'; ?></p>
      <p>Type: <?php echo $type ?: 'Yok'; ?></p>
      <p>Full URL: <?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?></p>
    </div>
    <?php endif; ?>
  </div>

  <script>
    // Şifre doğrulama için client-side kontrol
    document.addEventListener('DOMContentLoaded', function() {
      const form = document.querySelector('form');
      if (form) {
        form.addEventListener('submit', function(e) {
          const password = document.getElementById('password').value;
          const confirmPassword = document.getElementById('confirm_password').value;
          
          if (password.length < 6) {
            e.preventDefault();
            alert('Şifre en az 6 karakter olmalıdır.');
            return false;
          }
          
          if (password !== confirmPassword) {
            e.preventDefault();
            alert('Şifreler eşleşmiyor.');
            return false;
          }
          
          return true;
        });
      }
    });
  </script>
</body>
</html> 
