# Changelog

All notable changes to this project are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Added
- `PennylaneInvoiceTransmitter`, adapter of `InvoiceTransmitterInterface` of `tmonier/sylius-einvoice-transmission-plugin` ^0.2: finds or creates the customer in Pennylane, then imports the Factur-X PDF; success is logged as `deposited` with the Pennylane invoice and customer ids in the message.
- `PennylaneClient` for the Pennylane Company API v2: `GET` and `POST /api/external/v2/customers`, `POST /api/external/v2/customer_invoices/e_invoices/imports` (bearer token, no automatic retry).
- `FacturXBuyerReader` and `Buyer`: the buyer is read from the Factur-X XML; the customer `external_reference` is `siren-<SIREN>` for a buyer with a SIREN, `buyer-<fingerprint>` otherwise.
- Mapping of the failures to the statuses of the journal: 401 and 403, 429 (with `Retry-After`), 5xx, timeout, missing token, unreadable answer and invalid Factur-X give `error`; any other HTTP status from 300 up gives `rejected`. Messages carry the HTTP status only, never the token or a body.
- Configuration keys `api_token` (`PENNYLANE_API_TOKEN`), `base_url`, `timeout` and `http_client`.

### Notes
- To check against the Pennylane sandbox: `invoice_options` sent as a JSON-encoded multipart field; HTTP 400, 404 and 422 mapped to `rejected`.
- No claim is made on the status of Pennylane as an approved platform: check the official list of the DGFiP. No regulatory compliance is promised.
