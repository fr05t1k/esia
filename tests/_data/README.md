# Test fixtures

## RSA fixtures (`server.crt`, `server.key`, `server.csr`)

Used by `SignerPKCS7Test` and `OpenIdTest`. Standard self-signed RSA material:

```sh
openssl req -x509 -newkey rsa:2048 -nodes \
  -keyout server.key -out server.crt -days 3650 \
  -subj '/C=RU/CN=Tester'
# the private key is then (re)encrypted with the password "test"
openssl rsa -in server.key -out server.key -aes256 -passout pass:test
```

## GOST fixtures (`server-gost.crt`, `server-gost.key`)

Used by `OpenIdCliOpensslTest` and `CliSignerRoundTripTest` to exercise the
`CliSignerPKCS7` (GOST) signing path. They are a **self-signed GOST R 34.10-2001**
certificate/key pair (`id-GostR3410-2001-CryptoPro-A-ParamSet`, `CN=Tester GOST 2001`),
valid until 2068, with the private key protected by the password `test`.

They require an OpenSSL build with the GOST engine (e.g. `libengine-gost-openssl1.1`
plus `tests/openssl.cnf`, as configured in `.github/workflows/ci.yml`). To regenerate
an equivalent pair with a GOST-enabled OpenSSL:

```sh
# self-signed GOST-2001 certificate + key, key password "test"
openssl req -x509 -engine gost \
  -newkey gost2001 -pkeyopt paramset:A \
  -passout pass:test \
  -keyout server-gost.key -out server-gost.crt \
  -days 18250 -subj '/C=RU/CN=Tester GOST 2001'
```

`CliSignerRoundTripTest` signs a known message with `CliSignerPKCS7` and then verifies
the resulting detached PKCS#7 signature with:

```sh
openssl smime -engine gost -verify -binary -inform DER -noverify \
  -in <signature.der> -content <message>
```

## `non_readable_file`

An intentionally empty file whose mode is set to `000` in CI
(`chmod 000 tests/_data/non_readable_file`) to test unreadable-file error paths.
