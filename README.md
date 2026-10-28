# icm_bompastor

Gerador de escala **Oração Ininterrupta** — Igreja Cristã Maranata.

## Uso local (rede da igreja)

1. Execute `iniciar.bat` (requer [Node.js](https://nodejs.org/)).
2. Abra o endereço do terminal (ex.: `http://localhost:8080`).
3. No celular, use o IP na mesma Wi‑Fi.

## Uso na internet (Hostinger)

Salva os horários dos irmãos em **MySQL** com **PHP**.

Passo a passo completo: **[HOSTINGER.md](HOSTINGER.md)**

Resumo:

1. Importar `database.sql` no phpMyAdmin.
2. Copiar `api/config.example.php` → `api/config.php` e preencher o MySQL.
3. Enviar os arquivos para `public_html`.
4. Testar `https://seudominio.com/api/health`.
5. Admin: `/admin/` (usuário `01`, senha `icm2026` — configurável em `api/config.php`).

## Arquivos

| Arquivo        | Função                          |
|----------------|---------------------------------|
| `index.html`   | Criar escalas                   |
| `escala.html`  | Inscrição nos horários          |
| `api/`         | Backend PHP (Hostinger)         |
| `database.sql` | Tabela MySQL                    |
| `server.js`    | Servidor local (opcional)       |
