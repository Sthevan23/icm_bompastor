# Publicar na Hostinger (PHP + MySQL)

Os horários e nomes dos irmãos ficam no **MySQL**. O site continua sendo HTML/JS; a pasta `api/` grava e lê os dados.

## 1. Banco de dados

1. hPanel → **Bancos de dados MySQL** → criar banco e usuário (anote host, nome, usuário e senha).
2. Abra **phpMyAdmin** → selecione o banco → **Importar** → arquivo `database.sql` deste projeto.
3. Confira se a tabela `escalas` foi criada.

## 2. Configurar a API

1. No computador, copie `api/config.example.php` para `api/config.php`.
2. O Git já envia `api/config.hostinger.php` com o MySQL da Hostinger. Se preferir, copie `api/config.example.php` → `api/config.php` e edite:

```php
'db_host' => 'localhost',      // ou o host que a Hostinger mostrar
'db_name' => 'u123456_icm',
'db_user' => 'u123456_icm',
'db_pass' => 'sua_senha',
```

## 3. Enviar arquivos

Envie para `public_html` (FTP ou Gerenciador de arquivos):

- `index.html`, `escala.html`, `style.css`, `app.js`, `logo.svg`
- `.htaccess` (raiz)
- pasta `api/` inteira (**incluindo** `config.php`, **sem** subir senha no GitHub)

Não é obrigatório subir `server.js`, `iniciar.bat` nem a pasta `data/` na Hostinger.

## 4. Testar

1. Abra `https://seudominio.com/api/health` — deve aparecer `{"ok":true}`.
2. Abra `https://seudominio.com/` → **Nova** → crie uma escala (vai direto para o MySQL — qualquer celular do grupo vê).
3. Na escala, **Compartilhar** → mande esse link no WhatsApp (não use link antigo só do seu aparelho).
4. Abra o link da escala em outro celular e registre um horário.
5. Atualize a página: o nome deve permanecer (veio do banco).
6. Admin: `https://seudominio.com/admin/` — login **01** / **icm2026** (mude em `api/config.php`: `admin_user`, `admin_pass`).

Envie a pasta **`admin/`** junto com os demais arquivos.

## 5. Site em subpasta

Se o projeto ficar em `https://seudominio.com/oracao/`, em **index.html** e **escala.html** altere:

```html
<html lang="pt-BR" data-api-base="/oracao">
```

E envie também um `.htaccess` ajustado ou coloque os arquivos dentro de `public_html/oracao/`.

## Estrutura da tabela

| Coluna         | Uso                                      |
|----------------|------------------------------------------|
| `id`           | ID do link da escala (16 caracteres)     |
| `igreja`, `motivo`, `date0`, `date1` | Dados da escala        |
| `owner_token`  | Só no servidor; no navegador guarda quem criou |
| `current_list` | JSON com os 96 horários e nomes          |
| `atualizado_em`| Última inscrição ou alteração              |
