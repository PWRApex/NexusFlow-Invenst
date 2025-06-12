# NexusFlow Bütçe Yönetim Sistemi

## Proje Hakkında

NexusFlow, kişisel bütçe yönetimi için geliştirilmiş bir web uygulamasıdır. Kullanıcılar gelir ve giderlerini takip edebilir, bütçelerini yönetebilirler.

## Kurulum Adımları

### 1. XAMPP Kurulumu

Eğer bilgisayarınızda XAMPP kurulu değilse, [XAMPP'ın resmi sitesinden](https://www.apachefriends.org/index.html) indirip kurabilirsiniz.

### 2. Projeyi XAMPP'a Taşıma

1. Bu projeyi `C:\xampp\htdocs\nexusflow` klasörüne kopyalayın.
2. Eğer farklı bir klasör adı kullanmak isterseniz, bağlantıları buna göre güncellemeniz gerekebilir.

### 3. MySQL Veritabanı Kurulumu

1. XAMPP Kontrol Paneli'ni açın ve Apache ile MySQL servislerini başlatın.
2. Tarayıcınızda `http://localhost/phpmyadmin` adresine gidin.
3. Sol menüden "Yeni" seçeneğine tıklayarak `nexusflow_db` adında yeni bir veritabanı oluşturun.
4. Oluşturduğunuz veritabanını seçin ve "İçe Aktar" sekmesine tıklayın.
5. "Dosya Seç" butonuna tıklayarak projedeki `database.sql` dosyasını seçin ve "Git" butonuna tıklayın.

### 4. Veritabanı Bağlantı Ayarları

1. Projedeki `config.php` dosyasını açın.
2. Veritabanı bağlantı bilgilerini kontrol edin ve gerekirse düzenleyin:
   ```php
   $host = 'localhost'; // XAMPP için varsayılan host
   $dbname = 'nexusflow_db'; // Veritabanı adı
   $username = 'root'; // XAMPP için varsayılan kullanıcı adı
   $password = ''; // XAMPP için varsayılan şifre (boş)
   ```

### 5. Uygulamayı Çalıştırma

1. Tarayıcınızda `http://localhost/nexusflow` adresine gidin.
2. Karşınıza giriş ekranı gelecektir.
3. Veritabanında örnek bir kullanıcı oluşturulmuştur:
   - E-posta: `test@example.com`
   - Şifre: `password`
4. Bu bilgilerle giriş yapabilir veya yeni bir hesap oluşturabilirsiniz.

## Özellikler

- Kullanıcı kaydı ve girişi
- Bütçe yönetimi
- Gelir ve gider takibi
- Gelir ve gider kategorileri
- Gösterge paneli ile genel bakış

## Teknik Detaylar

- PHP 7.4+ ile geliştirilmiştir
- MySQL veritabanı kullanılmaktadır
- Bootstrap 5 ile responsive tasarım
- Font Awesome ikonları
- PDO ile güvenli veritabanı bağlantısı

## Güvenlik Önlemleri

- Şifreler PHP'nin `password_hash()` fonksiyonu ile hashlenmektedir
- XSS saldırılarına karşı veri temizleme
- SQL enjeksiyonlarına karşı prepared statements
- Oturum güvenliği

## Sorun Giderme

- Eğer "Bağlantı hatası" alıyorsanız, veritabanı bağlantı bilgilerinizi kontrol edin.
- Sayfa yüklenme sorunları yaşıyorsanız, XAMPP'ta Apache ve MySQL servislerinin çalıştığından emin olun.
- Dosya yolu hatası alıyorsanız, projeyi doğru klasöre kopyaladığınızdan emin olun.

## İletişim

Sorularınız veya önerileriniz için lütfen iletişime geçin.