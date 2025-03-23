# FitMatch - Landing Page

![FitMatch Logo](public/landing/assets/img/favicon.svg)

To jest repozytorium zawierające landing page dla projektu FitMatch - platformy łączącej trenerów personalnych z klientami.

## Jak uruchomić

### Wymagania

- Docker i Docker Compose
- Git

### Kroki

1. Sklonuj repozytorium:
   ```bash
   git clone https://github.com/USERNAME/fitmatch.git
   cd fitmatch
   ```

2. Uruchom środowisko Docker:
   ```bash
   docker-compose up -d
   ```

3. Zainstaluj zależności PHP:
   ```bash
   docker-compose exec php composer install
   ```

4. Landing page będzie dostępny pod adresem:
   ```
   http://localhost:8000
   ```

## Struktura projektu

### Landing Page