# Локальные модели Whisper

Чтобы **не качать** с Hugging Face (прокси/SSL):

```powershell
cd services\asr
powershell -File scripts\copy_whisper_from_cache.ps1
```

Скрипт берёт уже скачанную `faster-whisper-small` из кэша пользователя
(`%USERPROFILE%\.cache\huggingface\hub\…`) — ту же, что использует `C:\OpenServer\video`.

Либо вручную скопируйте snapshot-папку с `model.bin` сюда как `faster-whisper-small/`.

`model.bin` в git **не коммитится** (~480 МБ).
