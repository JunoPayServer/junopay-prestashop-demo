# JunoPay PrestaShop Demo

Deployable PrestaShop demo store for the JunoPay payment module.

The image installs PrestaShop 8.2 with an in-container MariaDB service, installs the `junopay` payment module from [`JunoPayServer/junopay-prestashop-plugin`](https://github.com/JunoPayServer/junopay-prestashop-plugin), and creates a `1 gallon of air` product priced at `1 JUNO`.

## Runtime configuration

- `PS_DOMAIN`: public host name.
- `JUNOPAY_BASE_URL`: Juno Pay Server base URL.
- `JUNOPAY_MERCHANT_API_KEY`: merchant API key used to create invoices.
- `JUNOPAY_WEBHOOK_SECRET`: reserved for webhook verification.

## Local run

```bash
docker build -t junopay-prestashop-demo:local .
docker run --rm -p 18085:80 \
  -e PS_DOMAIN=localhost:18085 \
  -e JUNOPAY_BASE_URL=https://staging.junopayserver.com \
  -e JUNOPAY_MERCHANT_API_KEY=replace-me \
  junopay-prestashop-demo:local
```
