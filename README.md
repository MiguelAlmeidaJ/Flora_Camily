# Flora Camily

E-commerce simples em PHP + MySQL para venda de adornos e homenagens florais, com carrinho, checkout próprio, gestão de pedidos e entrega realizada pela própria empresa.

## Stack

- PHP 8.1+
- MySQL/MariaDB
- Bootstrap 5
- Bootstrap Icons
- Apache / hospedagem compartilhada
- Composer para dependências PHP
- PHPMailer para envio SMTP
- Sem framework, mantendo compatibilidade com hospedagem compartilhada

## Fluxo do pedido

1. O cliente escolhe os produtos e pode adicionar ao carrinho ou usar **Finalizar compra** para ir direto ao checkout.
2. O pedido entra no painel como `Novo pedido` e aparece nas notificações.
3. A loja recebe também uma notificação por e-mail via SMTP usando PHPMailer.
4. O vendedor analisa o pedido e pode chamar o cliente pelo WhatsApp caso seja necessário ajustar algum detalhe.
5. Ao fechar a venda, o vendedor altera o status para `Em preparação`.
6. Quando o pedido sair, altera para `Em entrega`.
7. Depois da entrega, altera para `Entregue`.

Status disponíveis: `Novo pedido`, `Aguardando ajuste`, `Em preparação`, `Em entrega`, `Entregue` e `Cancelado`.

## Painel administrativo

O painel utiliza uma sidebar responsiva e muda conforme o perfil conectado.

### Menu Admin

- Início
- Financeiro
- Pedidos
- Produtos
- Categorias
- Notificações
- Configurações

O Admin pode acompanhar vendas, definir frete, atualizar status, gerenciar o catálogo e alterar a identidade visual básica do site, incluindo logo e favicon.

### Menu Dev

- Início
- Usuários
- Logs
- Configurações

O perfil Dev possui acesso completo ao sistema. Na tela inicial há atalhos para as rotinas operacionais da loja. Em Configurações, o Dev também possui ferramentas técnicas para:

- executar migrations pendentes;
- gerar backup SQL completo do banco;
- limpar cache e OPcache;
- testar envio de e-mail;
- verificar informações do ambiente;
- acessar logs internos.

## Recursos atuais

- Home responsiva seguindo a identidade oliva, rosé, terracota, dourado e creme
- Catálogo de produtos
- Menu `Coroas` alimentado pelas categorias cadastradas no painel
- Categorias iniciais: Coroa de Flores Simples, Coroa de Flores Mediana e Coroa de Flores Luxo
- Página de produto com `Adicionar ao carrinho` e `Finalizar compra`
- Carrinho flutuante exibido apenas quando existem itens
- Botão flutuante do WhatsApp
- Botão `Comprar pelo WhatsApp` no header
- Checkout com dados do cliente, homenageado, local, data, horário, frase da faixa e observações
- Frete manual definido pela Flora Camily
- Registro do pedido no MySQL
- Painel com notificações, financeiro, pedidos, produtos e categorias
- Cadastro de usuários `admin` e `dev`
- Logs de ações administrativas
- Logo e favicon configuráveis pelo painel
- Backup SQL pelo perfil Dev
- Gerenciamento de migrations pelo perfil Dev
- Proteção CSRF nos formulários e bloqueio de execução de PHP na pasta de uploads

## Instalação nova em hospedagem compartilhada

1. No painel da hospedagem, crie um banco MySQL e um usuário com acesso a esse banco.
2. Abra o banco no phpMyAdmin e importe `database.sql`.
3. Faça uma cópia de `config.local.example.php` com o nome `config.local.php`.
4. Em `config.local.php`, informe banco, WhatsApp, e-mails e credenciais SMTP da Flora Camily.
5. Envie todo o projeto para `public_html` ou para a pasta configurada para o domínio/subdomínio.
6. Dentro da pasta do projeto execute:

```bash
composer install --no-dev --optimize-autoloader
```

7. Garanta que PHP 8.1 ou superior esteja selecionado na hospedagem.
8. A pasta `uploads` precisa permitir gravação pelo PHP. Em hospedagens comuns, `755` costuma ser suficiente.
9. Acesse `seu-dominio.com/admin.php`.

`config.local.php` está no `.gitignore`, portanto as credenciais do servidor não precisam ser enviadas ao GitHub.

## Atualizando uma instalação existente

Depois de executar:

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
```

Aplique somente as migrations que ainda não foram executadas, nesta ordem:

```bash
mysql -h localhost -u USUARIO_DO_BANCO -p NOME_DO_BANCO < migrations/2026-09-17-order-flow.sql
mysql -h localhost -u USUARIO_DO_BANCO -p NOME_DO_BANCO < migrations/2026-09-17-categories-users-dev.sql
mysql -h localhost -u USUARIO_DO_BANCO -p NOME_DO_BANCO < migrations/2026-09-17-admin-sidebar-settings.sql
```

Não repita migrations já aplicadas. Depois da terceira migration, as próximas poderão ser acompanhadas e executadas em **Configurações > Migrations** pelo perfil Dev.

Na migração de perfis, o usuário existente `admin` passa inicialmente para `dev`, permitindo criar contas administrativas separadas.

## Configuração local

Exemplo de `config.local.php`:

```php
<?php

return [
    'whatsapp_number' => '5532999999999',
    'store_email' => 'pedidos@seudominio.com.br',
    'from_email' => 'pedidos@seudominio.com.br',
    'smtp' => [
        'enabled' => true,
        'host' => 'smtp.seudominio.com.br',
        'port' => 587,
        'encryption' => 'tls',
        'auth' => true,
        'username' => 'pedidos@seudominio.com.br',
        'password' => 'SENHA_DO_EMAIL',
        'from_name' => 'Flora Camily',
    ],
    'db' => [
        'host' => 'localhost',
        'name' => 'NOME_DO_BANCO',
        'user' => 'USUARIO_DO_BANCO',
        'pass' => 'SENHA_DO_BANCO',
    ],
];
```

`store_email` é o endereço que recebe a notificação de novo pedido. `from_email` é o remetente usado pelo SMTP e deve preferencialmente ser a mesma conta autenticada. Para a maioria das hospedagens, use porta `587` com `tls`; se o provedor exigir SMTPS, normalmente será porta `465` com `ssl`.

## Primeiro acesso administrativo

- Usuário: `admin`
- Senha: `Troque@123`
- Perfil inicial: `dev`

Altere a senha pelo próprio painel após o primeiro login.

## Estrutura principal

```text
assets/                    CSS, JS e identidade visual
includes/                  cabeçalho, rodapé e layout do painel
migrations/                alterações de banco
uploads/                   imagens de produtos e identidade do site
admin.php                  login e dashboard inicial
admin-financeiro.php       resumo financeiro
admin-pedidos.php          gestão dos pedidos
admin-produtos.php         gestão dos produtos
admin-categorias.php       gestão das categorias
admin-notificacoes.php     central de notificações
admin-usuarios.php         usuários DEV/Admin
admin-logs.php             logs técnicos
admin-configuracoes.php    configurações simples e ferramentas DEV
index.php                  página inicial
loja.php                   catálogo
produto.php                detalhe do produto e compra direta
carrinho.php               carrinho
checkout.php               checkout e criação do pedido
pedido-recebido.php        confirmação para o cliente
config.php                 configuração base e funções
config.local.php           credenciais locais, não versionadas
database.sql               estrutura completa para instalação nova
```

## Observação sobre pagamento

Esta versão registra o pedido e organiza o fluxo operacional da loja, mas ainda não processa pagamento on-line. O vendedor analisa a solicitação, define o frete manualmente e entra em contato com o cliente pelo WhatsApp caso algum ajuste seja necessário.
