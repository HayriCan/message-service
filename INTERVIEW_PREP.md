# Mülakat Hazırlık - Message Service

Bu doküman, proje hakkında sorulabilecek teknik mülakat sorularını ve cevaplarını içerir.

---

## 1. Mimari & Tasarım Kararları

### S: Neden Repository-Service pattern kullandın?

**Cevap:**
> Separation of concerns için. Repository sadece veritabanı işlemlerinden sorumlu, Service iş mantığını yönetiyor. Bu sayede:
> - **Test edilebilirlik**: Repository'yi mock'layarak Service'i izole test edebiliyorum
> - **Değiştirilebilirlik**: Yarın Eloquent yerine başka bir ORM kullanmak istersem sadece Repository'yi değiştirmem yeterli
> - **Single Responsibility**: Her katman tek bir işten sorumlu

### S: Neden Gateway pattern kullandın?

**Cevap:**
> Dış servislere (webhook) olan bağımlılığı izole etmek için.
> - Yarın webhook yerine Twilio, Firebase veya başka bir SMS provider gelirse sadece yeni bir Gateway yazarım
> - Service katmanı HTTP detaylarını bilmiyor, sadece `send()` metodunu çağırıyor
> - Exception handling izole: Gateway kendi exception'larını fırlatıyor (4xx, 5xx, connection), Service bunlara göre karar veriyor

### S: Neden DDD kullanmadın?

**Cevap:**
> Proje boyutu küçük, tek domain var (Message). DDD birden fazla bounded context olduğunda değer katıyor (Orders, Users, Payments gibi). Tek domain için DDD overhead'i gereksiz karmaşıklık yaratır. Mevcut yapı zaten SOLID prensiplerine uygun ve test edilebilir. Proje büyürse DDD'ye geçiş düşünülebilir.

### S: Laravel 12 neden seçtin? İsterlerde 10/11 yazıyordu.

**Cevap:**
> Güncel LTS/ekosistem ve PHP 8.2 ile tam uyum için. Kod yapısı Laravel 10/11 ile de uyumlu - sadece `composer.json`'daki constraint'i değiştirmek yeterli. Herhangi bir Laravel 12'ye özgü özellik kullanmadım.

---

## 2. Rate Limiting

### S: Rate limiting'i nasıl implemente ettin?

**Cevap:**
> Chunk-based delay stratejisi kullandım:
> 1. Mesajları 2'şerli gruplara (chunk) ayırıyorum
> 2. Her chunk'a artan delay veriyorum: 0s, 5s, 10s, 15s...
> 3. Bu sayede 5 saniyede maksimum 2 mesaj garantisi sağlanıyor
>
> ```php
> $chunks = $messages->chunk(2);
> foreach ($chunks as $index => $chunk) {
>     $delay = $index * 5; // saniye
>     // dispatch with delay
> }
> ```

### S: Neden Redis throttle kullanmadın?

**Cevap:**
> Redis throttle reactive (anlık kontrol), benim yaklaşımım proactive (önceden planlama). Her iki yaklaşım da geçerli. Benim tercihim:
> - Job'lar zaten planlanmış delay ile kuyruğa giriyor
> - Worker'lar arası race condition riski yok
> - Daha predictable davranış
>
> Redis throttle kullanılsaydı, rate limit aşıldığında job release edilip tekrar denenmesi gerekirdi - bu da ekstra complexity demek.

---

## 3. Queue & Job Processing

### S: Retry mekanizması nasıl çalışıyor?

**Cevap:**
> - **3 deneme hakkı** (`$tries = 3`)
> - **Exponential backoff**: 10s, 30s, 60s (`$backoff = [10, 30, 60]`)
> - **4xx hataları**: Retry yok, direkt failed (client hatası)
> - **5xx hataları**: Retry var (server hatası, geçici olabilir)
> - **Connection timeout**: Retry var
>
> Tüm retry'lar tükendikten sonra `failed()` metodu çağrılıyor ve mesaj FAILED olarak işaretleniyor.

### S: "Processing" durumunda takılan mesajlar ne oluyor?

**Cevap:**
> `--reset-stale` flag'i ile çözüyorum. 5 dakikadan uzun süredir PROCESSING durumunda kalan mesajları PENDING'e çekiyorum:
>
> ```bash
> php artisan messages:send --reset-stale
> ```
>
> Bu senaryo şu durumlarda oluşabilir:
> - Worker crash olursa
> - Deploy sırasında worker restart edilirse
> - Beklenmeyen bir exception olursa

### S: Concurrent execution'ı nasıl engelliyorsun?

**Cevap:**
> Laravel'in atomic lock mekanizmasını kullanıyorum:
>
> ```php
> Cache::lock('messages:send', 300)->get(function () {
>     // process messages
> });
> ```
>
> Aynı anda iki `messages:send` komutu çalışamaz. Lock 5 dakika sonra otomatik release oluyor.

---

## 4. Idempotency

### S: Idempotency key nedir, neden ekledin?

**Cevap:**
> Aynı mesajın birden fazla kez gönderilmesini önlemek için. Webhook tarafı bu key'e bakarak duplicate kontrolü yapabilir.
>
> Format: `msg_{message_id}_{created_at_timestamp}`
> Örnek: `msg_123_1702834567`
>
> Header olarak gönderiyorum:
> ```
> X-Idempotency-Key: msg_123_1702834567
> ```
>
> Bu özellikle retry senaryolarında önemli - network timeout oldu ama mesaj aslında iletildi, retry'da tekrar gönderilmemeli.

---

## 5. Exception Handling

### S: Exception hierarchy'ni açıklar mısın?

**Cevap:**
> ```
> WebhookException (abstract)
> ├── WebhookClientException (4xx) - isRetryable: false
> ├── WebhookServerException (5xx) - isRetryable: true
> └── WebhookConnectionException   - isRetryable: true
> ```
>
> - **4xx**: Client hatası, bizim tarafımızda bir sorun var (validation, auth), retry mantıksız
> - **5xx**: Server hatası, geçici olabilir, retry mantıklı
> - **Connection**: Timeout veya network hatası, retry mantıklı
>
> Job içinde:
> ```php
> catch (WebhookClientException $e) {
>     // No retry, mark as failed
> }
> catch (WebhookException $e) {
>     // Will retry
>     throw $e;
> }
> ```

---

## 6. Caching

### S: Redis cache'i ne için kullanıyorsun?

**Cevap:**
> Gönderilen mesaj bilgilerini 24 saat cache'liyorum:
>
> ```php
> Cache::put("message:{$id}", [
>     'message_id' => $webhookMessageId,
>     'sent_at' => now()->toIso8601String(),
> ], 24 hours);
> ```
>
> Kullanım senaryoları:
> - Hızlı lookup (DB'ye gitmeden)
> - Webhook callback'lerinde mesaj doğrulama
> - Monitoring/debugging

---

## 7. Testing

### S: Test stratejin nedir?

**Cevap:**
> **Unit Tests:**
> - Service metodları (izole, mock'lu)
> - Gateway HTTP davranışları (Http::fake ile)
> - Exception'lar
>
> **Feature Tests:**
> - API endpoint'leri (gerçek HTTP request)
> - Artisan command'ları
> - End-to-end flow (DB + Queue)
>
> Toplam: 45 test, 130 assertion

### S: Gateway'i nasıl test ediyorsun?

**Cevap:**
> Laravel'in `Http::fake()` özelliğiyle:
>
> ```php
> Http::fake([
>     'webhook.site/*' => Http::response([
>         'messageId' => 'test-id'
>     ], 202),
> ]);
>
> $response = $gateway->send('+90555...', 'Hello');
>
> Http::assertSent(function ($request) {
>     return $request->hasHeader('X-Idempotency-Key');
> });
> ```

---

## 8. Database

### S: Message tablosunun yapısını açıklar mısın?

**Cevap:**
> ```sql
> messages
> ├── id (PK)
> ├── phone_number (varchar 20)
> ├── content (text)
> ├── status (enum: pending, processing, sent, failed)
> ├── message_id (webhook'tan dönen ID, nullable)
> ├── sent_at (timestamp, nullable)
> ├── created_at
> └── updated_at
>
> Indexes:
> - status (sık filtreleme)
> - sent_at (sıralama)
> - (status, created_at) (composite - pending mesaj çekme)
> ```

### S: Neden 4 status var?

**Cevap:**
> - **PENDING**: Henüz işlenmemiş, kuyruğa alınmayı bekliyor
> - **PROCESSING**: Kuyruğa alındı, işleniyor (lock mekanizması)
> - **SENT**: Başarıyla gönderildi
> - **FAILED**: Tüm denemeler başarısız
>
> PROCESSING state'i önemli çünkü:
> - Aynı mesajın tekrar kuyruğa alınmasını engelliyor
> - Takılı kalan mesajları tespit edebiliyorum

---

## 9. Docker & DevOps

### S: Docker compose yapını açıklar mısın?

**Cevap:**
> 5 servis:
> - **app**: PHP-FPM (Laravel)
> - **nginx**: Web server (port 8080)
> - **mysql**: Database (port 3306)
> - **redis**: Cache & Queue (port 6379)
> - **queue**: Dedicated queue worker
>
> Queue worker ayrı container çünkü:
> - Bağımsız scale edilebilir
> - Crash olursa sadece worker restart olur
> - Resource isolation

### S: Production'da ne değişir?

**Cevap:**
> - Supervisor ile queue worker yönetimi
> - Horizon kullanılabilir (queue monitoring)
> - Redis Cluster (HA)
> - MySQL replica (read scaling)
> - Load balancer arkasında multiple app instance
> - Prometheus + Grafana monitoring

---

## 10. Geliştirme Önerileri

### S: Bu projeyi nasıl geliştirebilirsin?

**Cevap:**
> 1. **Circuit Breaker**: Webhook sürekli hata veriyorsa geçici olarak durdur
> 2. **Dead Letter Queue**: Kalıcı olarak başarısız mesajlar için ayrı kuyruk
> 3. **Prometheus Metrics**: Gönderim başarı oranı, latency, queue depth
> 4. **Webhook Callback**: Delivery status güncellemesi için callback endpoint
> 5. **Priority Queue**: Acil mesajlar için öncelikli kuyruk
> 6. **Batch API**: Toplu mesaj oluşturma endpoint'i

---

## Özet Cheat Sheet

| Konu | Anahtar Kelimeler |
|------|-------------------|
| Mimari | Repository-Service-Gateway, SOLID, SoC |
| Rate Limit | Chunk-based delay, 2 msg/5 sec, proactive |
| Retry | 3 tries, exponential backoff, 4xx no retry |
| Idempotency | X-Idempotency-Key header, duplicate prevention |
| Exception | Hierarchy, isRetryable(), 4xx vs 5xx |
| Queue | Redis, dedicated worker, atomic lock |
| Testing | 45 tests, Http::fake, mock gateway |
| Stale Recovery | --reset-stale, 5 min threshold |
