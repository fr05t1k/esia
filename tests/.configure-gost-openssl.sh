sed -i '1iopenssl_conf=openssl_def' /usr/lib/ssl/openssl.cnf
tee -a /usr/lib/ssl/openssl.cnf <<EOF

[openssl_def]
engines = engine_section

[engine_section]
gost = gost_section

[gost_section]
engine_id = gost
dynamic_path = /usr/lib/x86_64-linux-gnu/engines-1.1/gost.so
default_algorithms = ALL
CRYPT_PARAMS = id-Gost28147-89-CryptoPro-A-ParamSet

EOF
