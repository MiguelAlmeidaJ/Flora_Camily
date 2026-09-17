# Flora Camily

E-commerce simples em PHP + MySQL para venda de adornos e homenagens florais, com carrinho, checkout próprio, gestão de pedidos e entrega realizada pela própria empresa.

## Stack

- PHP 8.1+
- MySQL/MariaDB
- Bootstrap 5
- Bootstrap Icons
- Apache / hospedagem compartilhada
- Sem Composer e sem framework, para facilitar a publicação em `public_html`

## Fluxo do pedido

1. O cliente escolhe os produtos e pode adicionar ao carrinho ou usar **Finalizar compra** para ir direto ao checkout.
2. O pedido entra no painel como `Novo pedido` e aparece no sino de notificações.
3. A loja recebe também uma notificação por e-mail, quando o servidor possui `mail()` habilitado e os e-mails estão configurados.
4. O vendedor analisa o pedido e pode chamar o cliente pelo WhatsApp caso seja necessário ajustar algum detalhe.
5. Ao fechar a venda, o vendedor altera o status para `Em preparação`.
6. Quando o pedido sair, altera para `Em entrega`.
7. Depois da entrega, altera para `Entregue`.

Status disponíveis: `Novo pedido`, `Aguardando ajuste`, `Em preparação`, `Em entrega`, `Entregue` e `Cancelado`.

## Recursos atuais

- Home responsiva seguindo a identidade oliva, rosé, terracota, dourado e creme
- Catálogo de produtos
- Menu `Coroas` alimentado pelas categorias cadastradas no painel
- Categorias iniciais: Coroa de Flores Simples, Coroa de Flores Mediana e Coroa de Flores Luxo
- Tela administrativa para criar, editar, ordenar, ocultar e excluir categorias
- Página de produto com `Adicionar ao carrinho` e `Finalizar compra`
- Carrinho flutuante exibido apenas quando existem itens
- Botão flutuante do WhatsApp
- Botão `Comprar pelo WhatsApp` no header
- Carrinho com sessão PHP
- Checkout com dados do cliente, homenageado, local, data, horário, frase da faixa e observações
- Frete manual definido pela própria Flora Camily
- Registro do pedido no MySQL
- Tela de confirmação após a compra
- Painel administrativo com notificações de novos pedidos
- Atualização de status do pedido
- Definição manual do frete pelo painel
- Atalho para o vendedor chamar o cliente pelo WhatsApp
- Notificação de novo pedido por e-mail usando `mail()` do PHP
- Cadastro, edição, ativação e exclusão de produtos
- Upload de imagens JPG, PNG e WEBP
- Perfis `admin` e `dev`
- Área DEV para usuários, diagnóstico do servidor e logs da aplicação
- Alteração de senha
- Proteção CSRF nos formulários e bloqueio de execução de PHP na pasta de uploads

## Perfis de acesso

### Admin

Pode gerenciar:

- pedidos;
- frete e status;
- produtos;
- categorias;
- própria senha.

### Dev

Possui tudo que o Admin possui e também acesso a `admin-dev.php`, incluindo:

- criação e edição de usuários Admin/Dev;
- troca de perfil;
- diagnóstico de PHP, MySQL/MariaDB, uploads e e-mail;
- teste de envio de e-mail;
- logs internos das ações administrativas.

## Instalação nova em hospedagem compartilhada

1. No painel da hospedagem, crie um banco MySQL e um usuário com acesso a esse banco.
2. Abra o banco no phpMyAdmin e importe `database.sql`.
3. Faça uma cópia de `config.local.example.php` com o nome `config.local.php`.
4. Em `config.local.php`, informe banco, usuário, senha, WhatsApp e e-mails da Flora Camily.
5. Envie todo o projeto para `public_html` ou para a pasta configurada para o domínio/subdomínio.
6. Garanta que PHP 8.1 ou superior esteja selecionado na hospedagem.
7. A pasta `uploads` precisa permitir gravação pelo PHP. Em hospedagens comuns, `755` costuma ser suficiente.
8. Acesse `seu-dominio.com/admin.php`.

`config.local.php` está no `.gitignore`, portanto as credenciais do servidor não precisam ser enviadas ao GitHub.

## Atualizando uma instalação que já estava no servidor

Depois de executar:

```bash
git pull origin main
```

Se a instalação **ainda não recebeu** a migração do novo fluxo de pedidos, execute primeiro:

```bash
mysql -h localhost -u USUARIO_DO_BANCO -p NOME_DO_BANCO < migrations/2026-09-17-order-flow.sql
```

Depois execute uma única vez a migração de categorias, usuários e logs:

```bash
mysql -h localhost -u USUARIO_DO_BANCO -p NOME_DO_BANCO < migrations/2026-09-17-categories-users-dev.sql
```

Se `2026-09-17-order-flow.sql` já foi executada anteriormente, rode apenas a segunda migração. Não repita migrações já aplicadas.

Na migração de perfis, o usuário existente `admin` passa inicialmente para o perfil `dev`, permitindo criar os demais usuários pelo painel DEV.

## Configuração local

Exemplo de `config.local.php`:

```php
<?php

return [
    'whatsapp_number' => '5532999999999',
    'store_email' => 'pedidos@seudominio.com.br',
    'from_email' => 'naoresponda@seudominio.com.br',
    'db' => [
        'host' => 'localhost',
        'name' => 'NOME_DO_BANCO',
        'user' => 'USUARIO_DO_BANCO',
        'pass' => 'SENHA_DO_BANCO',
    ],
];
```

`store_email` é o endereço que recebe a notificação de novo pedido. `from_email` deve preferencialmente pertencer ao próprio domínio da hospedagem para reduzir bloqueios de envio.

## Primeiro acesso administrativo

- Usuário: `admin`
- Senha: `Troque@123`
- Perfil inicial: `dev`

Altere a senha pelo próprio painel após o primeiro login.

## Estrutura principal

```text
assets/                CSS, JS e identidade visual
includes/              cabeçalho e rodapé
migrations/            alterações de banco para instalações existentes
uploads/               imagens cadastradas pelo painel
admin.php              painel administrativo e gestão de pedidos/produtos
admin-categorias.php   gestão das categorias do menu Coroas
admin-dev.php          usuários, ferramentas e logs do perfil DEV
index.php              página inicial
loja.php               catálogo e filtro por categorias
produto.php            detalhe do produto e compra direta
carrinho.php           carrinho
checkout.php           checkout e criação do pedido
pedido-recebido.php    confirmação para o cliente
config.php             configuração base e funções
config.local.php       credenciais locais, não versionadas
database.sql           estrutura completa para instalação nova
```

## Observação sobre pagamento

Esta versão registra o pedido e organiza o fluxo operacional da loja, mas ainda não processa pagamento on-line. O vendedor analisa a solicitação, define o frete manualmente e entra em contato com o cliente pelo WhatsApp caso algum ajuste seja necessário.
