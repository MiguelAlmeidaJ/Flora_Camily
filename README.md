# Flora Camily

E-commerce em PHP + MySQL para venda de adornos e homenagens florais, com finalização do pedido pelo WhatsApp.

## Stack
- PHP 8.1+
- MySQL/MariaDB
- Bootstrap 5.3
- Bootstrap Icons
- Apache / hospedagem compartilhada

## Instalação em hospedagem compartilhada
1. Crie um banco MySQL e um usuário pelo painel da hospedagem.
2. Importe o arquivo `database.sql` pelo phpMyAdmin.
3. Edite `config.php` e informe os dados do banco e o número de WhatsApp da loja.
4. Envie os arquivos para `public_html` (ou para a pasta do domínio/subdomínio).
5. Garanta permissão de escrita na pasta `uploads` para o upload das imagens dos produtos.
6. Acesse `/admin.php`.

### Primeiro acesso administrativo
- Usuário: `admin`
- Senha: `Troque@123`

Troque a senha imediatamente após o primeiro login.

## Fluxo de compra
O cliente adiciona produtos ao carrinho, preenche os dados da homenagem/entrega e, ao finalizar, o site registra a solicitação no banco e abre o WhatsApp com a mensagem do pedido pronta.

## Configuração do WhatsApp
Em `config.php`, altere `WHATSAPP_NUMBER` para o número da loja com DDI + DDD + número, somente dígitos. Exemplo: `5532999999999`.
