готов
1) Учебный сервис предварительной оценки заявки на заём под ПТС: принимает заявку, считает LTV и возвращает `approve` / `review` / `reject`.
2) Makefile: `make up`, `make down`, `make ps`, `make logs`, `make install`, `make test`, `make lint`, `make seed`, `make help`; docker-compose.yml: backend запускается командой `php -S 0.0.0.0:8080 -t backend/public backend/public/router.php`, у MySQL есть healthcheck `mysqladmin ping`.
3) `backend/src/Domain` (`DecisionEngine.php`).
модель: training-2026-09-gpt-5.6-terra
