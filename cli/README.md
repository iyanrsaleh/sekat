# Pemasang Sekat

Program C++17. Perintahnya `sekat`.

```text
cmake -S cli -B cli/build
cmake --build cli/build --config Release
```

Dari folder yang berisi `sekat.exe`:

```text
sekat install --global
```

Proyek baru, di folder kosong:

```text
sekat install
sekat db
sekat migrate
sekat serve
```
