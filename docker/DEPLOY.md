# Production Deployment

Self-hosted single-VPS deployment for **The Mnemonic Technique**
(ImplementationPlan §5.4–5.6). The app is local-first: Ollama (`qwen3:14b`) runs
in-stack on the host GPU, so the target server needs an AMD GPU with ROCm
(`/dev/kfd`, `/dev/dri`) just like the dev machine.

Artifacts in this repo:

| File | Purpose |
|---|---|
| `.env.production` | Env template (gitignored) — copy to `.env` on the server, fill placeholders |
| `docker-compose.prod.yml` | Hardened standalone stack (no public DB/Redis/Ollama ports, TLS via nginx) |
| `docker/nginx/nginx.conf` | TLS termination + reverse proxy to the app container |

## Steps

```bash
# 1. Clone
git clone <repo-url> /var/www/mnemonic && cd /var/www/mnemonic

# 2. Env
cp .env.production .env
#    edit .env: set APP_URL, DB_*/REDIS_PASSWORD secrets (all <...> placeholders)
#    edit docker/nginx/nginx.conf: replace every `your-domain.com`

# 3. TLS cert (certbot, standalone — stop anything on :80 first)
sudo apt install -y certbot
sudo certbot certonly --standalone -d your-domain.com

# 4. Build front-end assets
npm ci && npm run build

# 5. Start the stack
docker compose -f docker-compose.prod.yml up -d

# 6. PHP deps (production)
docker compose -f docker-compose.prod.yml exec laravel.test \
    composer install --no-dev --optimize-autoloader

# 7. Laravel production setup
docker compose -f docker-compose.prod.yml exec laravel.test php artisan key:generate
docker compose -f docker-compose.prod.yml exec laravel.test php artisan migrate --force
docker compose -f docker-compose.prod.yml exec laravel.test php artisan config:cache
docker compose -f docker-compose.prod.yml exec laravel.test php artisan route:cache
docker compose -f docker-compose.prod.yml exec laravel.test php artisan view:cache
docker compose -f docker-compose.prod.yml exec laravel.test php artisan event:cache

# 8. Pull the model + verify GPU
docker compose -f docker-compose.prod.yml exec ollama ollama pull qwen3:14b
docker compose -f docker-compose.prod.yml exec ollama ollama ps   # must show "100% GPU"

# 9. Verify the app
curl -s -o /dev/null -w "%{http_code}\n" https://your-domain.com   # expect 200
```

## Scheduler (cron)

The daily `trash:purge` job (`routes/console.php`, 03:00) needs the scheduler
ticking. Add to the host crontab (`crontab -e`):

```cron
* * * * * cd /var/www/mnemonic && docker compose -f docker-compose.prod.yml exec -T laravel.test php artisan schedule:run >> /var/log/mnemonic-scheduler.log 2>&1
```

Confirm it is registered: `... php artisan schedule:list` shows `trash:purge`.

## Production readiness checklist (§5.6)

- [ ] `APP_DEBUG=false`, `APP_ENV=production`
- [ ] `APP_KEY` generated on the server (unique, not the dev key)
- [ ] HTTPS enforced; HTTP redirects to HTTPS; `SESSION_SECURE_COOKIE=true`
- [ ] MariaDB / Redis / Ollama expose **no** host ports (verified: none in `docker-compose.prod.yml`)
- [ ] Redis password set (`--requirepass`, matches `REDIS_PASSWORD`)
- [ ] Horizon dashboard behind authentication (gate allows any authenticated user)
- [ ] `.env` not committed; `composer.lock` + `package-lock.json` committed
- [ ] `php artisan migrate:status` — all `Ran`
- [ ] Horizon shows workers running; `schedule:list` shows `trash:purge`
- [ ] `ollama ps` shows `100% GPU`
- [ ] `LOG_CHANNEL=daily`, `LOG_LEVEL=error`
- [ ] `storage/` + `bootstrap/cache/` writable by the web user
- [ ] Re-run `php artisan config:cache` after any `.env` change
- [ ] Backup strategy for the `mnemonic-mariadb` volume — **Product Owner decision** (out of plan scope)
