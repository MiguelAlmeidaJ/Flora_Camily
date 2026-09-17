# Flora Camily

E-commerce simples em PHP + MySQL para venda de adornos e homenagens florais, com carrinho e finalização do atendimento pelo WhatsApp.

## Stack

- PHP 8.1+
- MySQL/MariaDB
- Bootstrap 5
- Bootstrap Icons
- Apache / hospedagem compartilhada
- Sem Composer e sem framework, para facilitar a publicação em `public_html`

## Recursos atuais

- Home responsiva seguindo a identidade oliva, rosé, terracota, dourado e creme
- Catálogo por categorias
- Página de produto
- Carrinho com sessão PHP
- Checkout com local da homenagem, horário, mensagem da faixa e observações
- Registro da solicitação no MySQL
- Redirecionamento para o WhatsApp com o pedido pronto
- Painel administrativo
- Cadastro, edição, ativação e exclusão de produtos
- Upload de imagens JPG, PNG e WEBP
- Visualização das solicitações recentes
- Alteração de senha do administrador
- Proteção CSRF nos formulários e bloqueio de execução de PHP na pasta de uploads

## Instalação em hospedagem compartilhada

1. No painel da hospedagem, crie um banco MySQL e um usuário com acesso a esse banco.
2. Abra o banco no phpMyAdmin e importe `database.sql`.
3. Faça uma cópia de `config.local.example.php` com o nome `config.local.php`.
4. Em `config.local.php`, informe banco, usuário, senha e o WhatsApp real da Flora Camily.
5. Envie todo o projeto para `public_html` ou para a pasta configurada para o domínio/subdomínio.
6. Garanta que PHP 8.1 ou superior esteja selecionado na hospedagem.
7. A pasta `uploads` precisa permitir gravação pelo PHP. Em hospedagens comuns, `755` costuma ser suficiente.
8. Acesse `seu-dominio.com/admin.php`.

`config.local.php` está no `.gitignore`, portanto as credenciais do servidor não precisam ser enviadas ao GitHub.

## Primeiro acesso administrativo

- Usuário: `admin`
- Senha: `Troque@123`

Altere a senha pelo próprio painel após o primeiro login.

## Configuração do WhatsApp

Use DDI + DDD + número, somente dígitos. Exemplo:

```php
'whatsapp_number' => '5532999999999',
```

## Estrutura principal

```text
assets/          CSS, JS e identidade visual
includes/        cabeçalho e rodapé
uploads/         imagens cadastradas pelo painel
admin.php        painel administrativo
index.php        página inicial
loja.php         catálogo
produto.php      detalhe do produto
carrinho.php     carrinho
checkout.php     coleta dados e envia ao WhatsApp
config.php       configuração base e funções
config.local.php credenciais locais, não versionadas
database.sql     estrutura do MySQL
```

## Observação sobre pedidos

O site não processa pagamento on-line nesta primeira versão. O pedido é registrado como `whatsapp_iniciado` e o atendimento é concluído pela equipe, que confirma disponibilidade, composição, prazo, entrega e pagamento.
