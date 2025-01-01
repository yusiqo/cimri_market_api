# Cimri Market API

Cimri.com market ürünlerini programatik olarak aramak, fiyatları karşılaştırmak ve en uygun fiyatları bulmak için geliştirilmiş bir API.

## Video Anlatım

API'nin kurulumu ve kullanımı ile ilgili detaylı video anlatımına aşağıdaki linkten ulaşabilirsiniz:

<div align="center">
  <a href="https://www.youtube.com/watch?v=wB9-yZm_OmM">
    <img src="https://img.youtube.com/vi/wB9-yZm_OmM/maxresdefault.jpg" alt="Cimri Market API Kullanım Rehberi" style="width:100%;max-width:720px">
  </a>
</div>

Video İçeriği:
- API'nin kurulumu
- Endpoint'lerin kullanımı
- Örnek aramalar
- Önbellek yönetimi
- Güvenlik önlemleri
- Sık karşılaşılan sorunlar ve çözümleri

## Özellikler

### API Endpointleri

- `/api.php`: Ürün arama endpoint'i
- `/product.php`: Ürün detay endpoint'i
- `/image.php`: Ürün resmi endpoint'i
- `/logo.php`: Market logosu endpoint'i
- `/docs.php`: API dokümantasyonu

### Güvenlik ve Performans Özellikleri

#### Önbellekleme (Caching)
- Her istek için otomatik önbellekleme
- Önbellek süresi: 1 saat
- Maksimum önbellek dosya sayısı: 500
- Otomatik önbellek temizleme
- Süresi dolmuş dosyaların otomatik silinmesi

#### Rate Limiting Koruması
- Rastgele bekleme süreleri (1-3 saniye)
- Otomatik retry mekanizması (maksimum 3 deneme)
- Rate limit aşımında akıllı bekleme
- HTTP 429 (Too Many Requests) yönetimi

#### User Agent Rotasyonu
- Farklı tarayıcılar için user agent havuzu
- Her istek için rastgele user agent seçimi
- Tarayıcıya özel header yönetimi
- Modern tarayıcı simülasyonu

#### Hata Yönetimi
- İstek başarısızlıklarında otomatik yeniden deneme
- HTTP durum kodu kontrolü
- CURL hata yönetimi

### Arama Özellikleri

#### Filtreleme
- Kelime bazlı arama
- Türkçe karakter desteği
- Sayfalama desteği
- Sıralama seçenekleri:
  - En düşük fiyat (`price-asc`)
  - En düşük birim fiyat (`specUnit-asc`)

#### Ürün Bilgileri
- Ürün adı ve markası
- Fiyat bilgisi
- Birim fiyat
- Miktar ve birim
- Ürün resmi
- Market bilgileri

#### Ürün Detayları
- Detaylı ürün açıklaması
- Ürün özellikleri
- Fiyat geçmişi
- Market teklifleri
- Market logoları

## Kurulum

1. Projeyi klonlayın
2. PHP 7.4 veya üzeri sürüm gereklidir
3. PHP'nin curl ve json eklentileri aktif olmalıdır
4. Web sunucusunu başlatın:
```bash
php -S localhost:8000
```

## Kullanım

### Ürün Arama
```
GET /api.php?q=seker
GET /api.php?q=seker&sort=price-asc
GET /api.php?q=seker&page=2
```

### Ürün Detayları
```
GET /product.php?path=torku-toz-seker-1-kg-p-123456
```

### Resim ve Logo
```
GET /image.php?file=urun-resmi.jpg&size=md
GET /logo.php?id=12345
```

## Önbellek Yönetimi

- Önbellek dosyaları `cache/` dizininde saklanır
- Her dosya için benzersiz MD5 hash kullanılır
- Maksimum 500 dosya sınırı vardır
- Otomatik temizleme:
  - Uygulama başlatıldığında
  - Yeni cache yazılırken
  - Limit aşıldığında
  - Süresi geçmiş dosyalar için

## Güvenlik Önlemleri

1. Rate Limiting
   - Rastgele bekleme süreleri
   - Akıllı retry mekanizması
   - Rate limit aşımında uzun bekleme

2. User Agent Rotasyonu
   - Gerçekçi tarayıcı bilgileri
   - Her istek için farklı user agent
   - Tarayıcıya uygun headerlar

3. Hata Yönetimi
   - Detaylı loglama
   - Otomatik retry
   - Timeout kontrolü
