---
paths:
  - '**'
---

# General

## 採用官方文件的寫法
一律使用官方文件（Laravel、Inertia、Laravel AI SDK 等）示範的方法和寫法，即使有效果相同的底層寫法也不要用。例如用 `$this->travelTo()`，不要用 `Carbon::setTestNow()`。寫程式碼前先用 `search-docs` 查文件。這樣除錯時，每段程式碼都能直接在文件中查到。
