param([string]$BaseUrl = 'http://localhost:8000')
$ErrorActionPreference = 'Stop'
$script:checks = 0
function Check([bool]$Ok, [string]$Message) {
    if (-not $Ok) { throw $Message }
    $script:checks++
}
function Status([scriptblock]$Request) {
    try { return (& $Request).StatusCode } catch {
        if ($_.Exception.Response) { return [int]$_.Exception.Response.StatusCode }
        throw
    }
}
$homePage = Invoke-WebRequest "$BaseUrl/" -UseBasicParsing -SessionVariable customer
Check ($homePage.StatusCode -eq 200) 'Beranda gagal.'
Check (([regex]::Matches($homePage.Content, 'class="game-card"')).Count -eq 8) 'Jumlah game salah; gunakan database demo awal untuk smoke test.'
Check ($homePage.Headers['Content-Security-Policy'].Contains("script-src 'self'")) 'CSP tidak aktif.'
$page = Invoke-WebRequest "$BaseUrl/games/free-fire" -UseBasicParsing -WebSession $customer
Check (-not $page.Content.Contains('name="account[server_id]"')) 'Free Fire meminta server.'
$csrf = [regex]::Match($page.Content, 'name="_token" value="([^"]+)"').Groups[1].Value
$requestId = [regex]::Match($page.Content, 'name="request_id" value="([^"]+)"').Groups[1].Value
$productId = [regex]::Match($page.Content, 'name="product_id" value="([0-9]+)"').Groups[1].Value
Check ($csrf -ne '' -and $requestId -ne '' -and $productId -ne '') 'Form/token tidak lengkap.'
$payload = @{ _token=$csrf; request_id=$requestId; product_id=$productId; 'account[user_id]'='12345678'; whatsapp='081234567890'; price='1'; status='PAID' }
$created = Invoke-WebRequest "$BaseUrl/games/free-fire/orders" -Method POST -Body $payload -UseBasicParsing -WebSession $customer
$reference = [regex]::Match($created.Content, 'STZ-[A-Z0-9]{20}').Value
$detailUrl = $created.BaseResponse.ResponseUri.AbsoluteUri
Check ($reference -ne '' -and $created.Content.Contains('SIMULASI') -and $created.Content.Contains('Rp 15.000')) 'Pesanan/harga server gagal; gunakan harga demo awal.'
$again = Invoke-WebRequest "$BaseUrl/games/free-fire/orders" -Method POST -Body $payload -UseBasicParsing -WebSession $customer
Check ($again.Content.Contains($reference)) 'Submit ulang membuat referensi berbeda.'
Check ($created.Content.Contains('No. invoice') -and $created.Content.Contains('Cetak / Simpan PDF')) 'Invoice tidak tampil.'
$invoiceToken = [regex]::Match($created.Content, 'name="_token" value="([^"]+)"').Groups[1].Value
$methodPage = Invoke-WebRequest "$detailUrl/payment/method" -Method POST -Body @{_token=$invoiceToken; method='DEMO_QRIS'} -UseBasicParsing -WebSession $customer
Check ($methodPage.Content.Contains('Simulasikan pembayaran berhasil')) 'Metode pembayaran demo tidak dipilih.'
$paidPage = Invoke-WebRequest "$detailUrl/payment/simulate" -Method POST -Body @{_token=$invoiceToken; outcome='PAID'} -UseBasicParsing -WebSession $customer
Check ($paidPage.Content.Contains('Pembayaran berhasil') -and $paidPage.Content.Contains('DM belum dikirim')) 'Simulasi pembayaran berhasil tidak tercatat.'
$paymentStatus = (Invoke-WebRequest "$detailUrl/payment/status" -UseBasicParsing -WebSession $customer).Content | ConvertFrom-Json
Check ($paymentStatus.status -eq 'PAID') 'Status invoice tidak PAID.'
Check ((Status { Invoke-WebRequest $detailUrl -UseBasicParsing }) -eq 404) 'Pesanan bocor ke sesi lain.'
$invalid = $payload.Clone(); $invalid._token = 'invalid'
Check ((Status { Invoke-WebRequest "$BaseUrl/games/free-fire/orders" -Method POST -Body $invalid -UseBasicParsing -WebSession $customer }) -eq 419) 'CSRF tidak ditolak.'
$adminPage = Invoke-WebRequest "$BaseUrl/admin" -UseBasicParsing
Check ($adminPage.Content.Contains('autocomplete="current-password"')) 'Admin tidak meminta login.'
foreach ($path in @('/.env','/submit.php','/callback.php','/storage/app/private/legacy-20261102/db.php')) {
    Check ((Status { Invoke-WebRequest "$BaseUrl$path" -UseBasicParsing }) -in @(403,404)) "Source terbuka: $path"
}
Check ((Invoke-WebRequest "$BaseUrl/store.css" -UseBasicParsing).StatusCode -eq 200) 'Aset CSS gagal.'
Write-Output "$script:checks pemeriksaan HTTP lulus. Pesanan demo $reference dibuat pada database lokal."
