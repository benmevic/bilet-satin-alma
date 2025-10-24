# MERİCBİLET – Çok Rollü Otobüs Bileti Platformu

Görev dökümanındaki gereksinimlere uygun şekilde, çok rollü (Admin, Firma Admin, User) mimaride geliştirilmiş bir otobüs bileti satış uygulaması. Sefer arama, koltuk seçimi, kupon kullanımı, sanal cüzdan (bakiye), bilet iptal/iade ve PDF bilet üretimi özelliklerini içerir.

Bu doküman; Docker ile hızlı kurulum, test hesapları ve temel kullanım senaryolarını özetler. Örneklerle verilmiştir ve birebir başka bir projeye benzemeyecek şekilde yeniden yazılmıştır.

---

## 1) Neler Sunar?

- Sefer arama ve detay görüntüleme (ziyaretçilere açık)
- Koltuk seçimi (dolu koltuklar pasif/disabled)
- Kupon kodu ile indirim
- Sanal cüzdan (bakiye) ile bilet satın alma
- Biletlerim ekranı: PDF çıktı alma, iptal
- İptal/iade kuralı: Kalkışa ≤ 1 saat kala iptal yok, aksi halde ücret iadesi
- Firma Admin: Kendi firmasına ait sefer CRUD + firma bazlı kupon yönetimi
- Admin: Firma ve Firma Admin yönetimi + global kuponlar

---

## 2) Teknoloji Yığını

- Backend: PHP 8.1 (PDO/SQLite)
- Veritabanı: SQLite (tek dosya)
- Arayüz: HTML, CSS, Bootstrap 5
- Paketleme: Docker (Apache + mod_rewrite)
- PDF/QR: TCPDF ve PHP QR kütüphaneleri (vendor/)

---

## 3) Hızlı Kurulum (Docker)

Önkoşul: Docker Desktop kurulu ve çalışır olmalı.

Klasör yapınız (özet):
```
.
├─ admin/
├─ api/
├─ database/
│  └─ veritabani.db           ← SQLite dosyası
├─ includes/
│  └─ db.php                  ← sqlite yolunu database/veritabani.db gösterir
├─ public/
│  ├─ index.php
│  └─ .htaccess               ← Rewrite ve index yönlendirme
├─ uploads/
│  └─ company-logos/
├─ Dockerfile
├─ docker-compose.yml
└─ .dockerignore
```

Başlatmak için proje kök dizininde:

```bash
docker compose up -d
# veya
docker-compose up -d
```

Açın:
- Uygulama: http://localhost:8080/

Kapatmak için:
```bash
docker compose down
```

Yeniden derleme:
```bash
docker compose build --no-cache
docker compose up -d
```

Loglar:
```bash
docker compose logs -f
```

Container içine bağlanma:
```bash
docker exec -it yavuzlar-bilet-app bash
```

Notlar:
- Veritabanı ve upload’lar host makinenizde `./database` ve `./uploads` klasörleri ile kalıcıdır (compose volume eşlemesi).
- 403 Forbidden yaşarsanız Apache `DocumentRoot` ve `public/.htaccess` kontrollerini yapın; bu repo Dockerfile’ı `public/` köküne yönlendirecek şekilde yapılandırılmıştır.

---

## 4) Test Hesapları

Aşağıdaki hesaplar veritabanında (SQLite) test amaçlı oluşturulmuştur. Şifreler geliştirme ortamına göredir.

### Admin
| Ad | E-posta | Şifre |
|---|---|---|
| Sistem Yöneticisi | admin@mericbilet.com | admin123 |
| admin hesap | admin@test | 123123 |

### Firma Admin (Şirket Yöneticileri)
| Firma | E-posta | Şifre |
|---|---|---|
| Örnek Otobüs A.Ş. | ornek@test.com | 123456 |
| Meriç Turizm | meric@test.com | 123456 |
| Yavuzlar Turizm | yavuzlar@test.com | 123456 |
| Pamukkale Turizm | pamukkale@test.com | 123456 |
| Metro Turizm | metro@test.com | 123456 |

### Yolcu (User)
| Ad | E-posta | Şifre |
|---|---|---|
| meric aytas | meraytas_1025@hotmail.com | 123123 |
| test tested | test@test.com | 123456 |


> Not: Bakiye (balance) sahası test verilerinde farklı değerler içerebilir. Satın alma akışında bakiye kontrolü yapılır. Gerekirse Admin panelinden veya doğrudan DB’den güncellenebilir.

---

## 5) Örnek Akışlar

- Ziyaretçi
  1. Ana sayfadan kalkış/varış ve tarih seçerek sefer ara
  2. Detay sayfasına gir, koltuk durumunu incele (satın alma için giriş gerekir)

- User (Yolcu)
  1. Giriş yap veya kayıt ol
  2. Sefer ara → koltuk seç
  3. Kupon varsa uygula
  4. “Bakiye Yükle” ile cüzdanını doldur, satın al
  5. Biletlerim → PDF indir, iptal et (kalkışa ≥ 1 saat varsa iade al)

- Firma Admin
  1. Giriş yap
  2. Kendi firmasına ait seferleri ekle/güncelle/sil
  3. Firma içi kupon oluştur/düzenle/sil

- Admin
  1. Firmalar: ekle/güncelle/sil
  2. Firma Admin kullanıcılarını oluştur ve firmaya ata
  3. Tüm firmalarda geçerli kuponları yönet


