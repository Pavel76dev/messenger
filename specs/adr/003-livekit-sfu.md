# ADR-003: LiveKit SFU для звонков

- **Статус:** accepted
- **Дата:** 2026-09-24
- **Контекст:** фичи 009–011 (звонки, группы, screen share)

## Контекст

Нужны аудио/видеозвонки и демонстрация экрана в личных и групповых чатах
(до ~20 участников), с заделом на веб / мобильные / десктоп клиенты.
Медиа нельзя гонять через Laravel или Echo.

## Решение

1. Self-hosted **LiveKit Server** (SFU) рядом с OpenServer / на VPS (Docker).
2. Laravel выдаёт **LiveKit JWT** участникам звонка (`LIVEKIT_API_KEY` / `SECRET`).
3. Сигналинг звонка (invite / accept / reject / end / ringing) — через существующий
   Echo + private каналы `user.{id}` / `conversation.{id}` (ADR-002).
4. Медиа (WebRTC) — напрямую клиент ↔ LiveKit; coturn для TURN за NAT/LTE.

## Альтернативы

1. **WebRTC mesh** — ок только для 1:1; группы не масштабируются.
2. **Daily.co / Agora SaaS** — быстрее старт, но внешняя зависимость и меньше контроля.
3. **mediasoup с нуля** — гибко, но тяжелее в поддержке для учебного проекта.

## Последствия

- Плюсы: 1:1 и группы одной моделью; официальные SDK (JS, iOS, Android, RN, Flutter);
  один инстанс хватает на ~20 участников.
- Минусы / долг: отдельный процесс Docker + TURN; HTTPS обязателен вне localhost;
  iOS Safari ограничен по `getDisplayMedia` (нативный SDK позже).
