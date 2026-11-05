---
paths:
  - '**/*.php'
---

# Php

## 陣列處理用 Laravel Collection
處理陣列時優先用 `collect(...)->map()` / `pluck()` / `chunk()` 等 Collection 方法，不要用 `array_map`、`array_column`、`array_chunk` 等原生函式。
例外：方法回傳型別是 `list<...>` 時，Larastan 會把 `->values()->all()` 推斷成 `array<int, ...>`，這時保留外層的 `array_values(...)` 讓 PHPStan 通過（參考 `KaohsiungYouBikeClient::fetchStations`）。

## 多個鏈式方法要分行
依照 PER Coding Style 4.7 和 Laravel 的寫法：一條鏈有三個以上呼叫時就分行，每個 `->` 各自一行，縮排一層。兩個呼叫的維持同一行（例如 `BikeStation::factory()->create()`、`$query->where(...)->where(...)`）。
- 開頭是變數（包括 `$this`）時，變數單獨一行，第一個方法也要換行。
- 開頭是靜態呼叫（`Class::method()`）或函式（`collect(...)`、`fake()`）時，開頭的呼叫留在第一行。
- 傳入多個元素的陣列參數也展開成一行一個。
```php
$formosa = BikeStation::factory()
    ->state([
        'name' => 'YouBike2.0_捷運美麗島站',
        'district' => '新興區',
    ])
    ->create();

$hourly = $station
    ->snapshots()
    ->where('recorded_at', '>=', now()->subHours(6))
    ->get();
```
