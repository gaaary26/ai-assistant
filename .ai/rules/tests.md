---
paths:
  - 'tests/**'
  - 'database/seeders/**'
---

# Tests

## Factory 參數用 state() 傳入
Factory 的屬性覆寫一律用 `->state([...])`，不要當成 `create()` / `make()` 的參數。測試和 seeder 都適用。
- 正確：`BikeStation::factory()->empty()->state(['district' => '新興區'])->create()`
- 錯誤：`BikeStation::factory()->create(['district' => '新興區'])`

## 測試檔路徑對應受測類別
測試檔放在跟受測類別相同的相對路徑（去掉 `app/`），檔名 `{ClassName}Test.php`，一個類別一個測試類別。用到框架（資料庫、HTTP、container）的放 `tests/Feature`，純 PHP 邏輯放 `tests/Unit`。
- `app/Ai/Tools/FindNearbyBikeStations.php` → `tests/Feature/Ai/Tools/FindNearbyBikeStationsTest.php`
- 沒有類別的檔案照檔案路徑放：`routes/console.php` → `tests/Feature/Routes/ConsoleTest.php`
用 `php artisan make:test Ai/Tools/FindNearbyBikeStationsTest --phpunit` 建立。

## TestCase 已全域阻擋未 fake 的 HTTP 請求
`tests/TestCase.php` 的 `setUp()` 已呼叫 `Http::preventStrayRequests()`，所有測試都會阻擋沒有 fake 的 HTTP 請求。個別測試不要再呼叫它，只要用 `Http::fake()` 設定需要的回應。
