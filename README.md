# 406 Store

## Publicação no servidor

1. Atualize o repositório: `git pull origin main`.
2. Confirme que PHP possui as extensões `pdo_sqlite` e `curl`.
3. Crie a pasta gravável do banco: `mkdir -p data && chmod 775 data`.
4. Copie `config.example.php` para `config.php` e informe o handle e URLs da InfinitePay.
5. Abra `/install.php` uma única vez para criar o administrador. Depois use `/admin.php`.

## Arquivos visuais necessários

Envie para `assets/`:
- `logo406.png`
- `GG_v3.png`

O banco `data/store.sqlite` e `config.php` ficam fora do Git por segurança.

## Frete e rastreio

O checkout consulta a API Preço dos Correios e permite ao comprador escolher o serviço disponível. Em `config.php`, configure o token de produção, o CEP de origem em Belo Horizonte, o peso e as dimensões reais da embalagem. Os códigos de serviço também podem ser ajustados em `correios_services` conforme o contrato.

No painel, cada pedido com frete exibe um campo de código de rastreio. Ao salvar, o sistema tenta enviar o código ao e-mail do comprador usando a função de e-mail da VPS. Configure um serviço de e-mail/SMTP da hospedagem para garantir a entrega.
