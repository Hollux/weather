# Sauvegarde hors-site de la base — weather

> Comment les dumps SQL du projet weather sont répliqués sur Google Drive,
> **en clair** (données météo non sensibles), et comment tout reconstruire si
> ce serveur est perdu.
>
> Mis en place le 2026-09-04. Fichier destiné à être déposé dans Google Drive
> (hors du dépôt git — voir `.gitignore`).

---

## 1. Vue d'ensemble

```
┌─ serveur (VM Proxmox, Ubuntu 22.04) ──────────────────────────────┐
│                                                                    │
│  ~05:00 local  conteneur "backup" (fradelg/mysql-cron-backup)      │
│                (docker-compose.prod.yaml, CRON_TIME=0 3 * * * UTC)  │
│                mysqldump → /home/hollux/web/weather/backups/       │
│                AAAAMMJJHHMM.db_meteo.sql.gz  (garde les 7 derniers) │
│                                                                    │
│  05:30 local   cron → ~/scripts/backup-offsite/sync-weather.sh     │
│                rclone sync (SANS chiffrement) → Google Drive        │
│                                                                    │
└────────────────────────────────────────────────────────────────────┘
                                   │
                                   ▼
        Google Drive : Dev Web / backups_web / weather-backups /
            daily/            → miroir des 7 derniers dumps (noms EN CLAIR)
            versions/AAAAMMJJ/ → dumps sortis de la rotation locale
                                 (déplacés, JAMAIS supprimés — rétention = 0)
```

- **Pas de chiffrement** : les fichiers sur le Drive sont lisibles/téléchargeables
  directement depuis l'interface web, identiques aux `.sql.gz` locaux.
- **Miroir + versions** : `daily/` = les 7 fichiers locaux. Tout dump que la
  rotation locale supprime est *déplacé* dans `versions/AAAAMMJJ/` → historique
  complet conservé hors-site, sans purge (données jugées précieuses).
- Alerte mail (msmtp) si un `sync-weather.sh` échoue, cooldown 12 h.

> Remarque : chaque dump est un `mysqldump` **complet** de la base — le plus
> récent contient donc tout l'historique. `versions/` sert à la restauration à
> une date passée précise, pas à éviter une perte de données.

---

## 2. Configuration sur le serveur (instantané)

### Binaire rclone

| | |
|---|---|
| Chemin | `/home/hollux/.local/bin/rclone` |
| Version | `rclone v1.75.0` |
| Installé sans root | dézippé depuis <https://downloads.rclone.org/rclone-current-linux-amd64.zip> |

> Le même rclone sert aussi à la sauvegarde du projet `timeline` (remote `gcrypt`,
> chiffré). Ici on n'utilise que `gdrive_weather`.

### `~/.config/rclone/rclone.conf` — section utile (permissions `600`)

```ini
[gdrive_weather]
type = drive
scope = drive.file                       # accès limité aux fichiers créés par rclone
client_id =                              # client OAuth intégré de rclone (voir §6)
client_secret =
team_drive =
token = { ... }                          # jeton OAuth — partagé avec gdrive_hollux, se renouvelle seul
root_folder_id = 1E1oxCbHtazpz1jk-LFXRGIQ31EJHRv1M   # = dossier "weather-backups"
```

> `root_folder_id` pointe sur le dossier **`weather-backups`**, à ranger dans
> `Dev Web/backups_web/` sur le Drive. Comme il a été **créé par rclone**, le
> scope minimal `drive.file` suffit même après déplacement manuel.
> Dossier : <https://drive.google.com/drive/folders/1E1oxCbHtazpz1jk-LFXRGIQ31EJHRv1M>
>
> Pas de remote `crypt` : la synchro écrit directement dans `gdrive_weather:`.

### Script & planification

| | |
|---|---|
| Script | `/home/hollux/scripts/backup-offsite/sync-weather.sh` |
| Logs | `.../backup-offsite/sync-weather.log` et `cron-weather.log` |
| Cron (`crontab -l`) | `30 5 * * * /home/hollux/scripts/backup-offsite/sync-weather.sh >> …/cron-weather.log 2>&1` |
| Cible rclone | `gdrive_weather:daily` + `gdrive_weather:versions/AAAAMMJJ` |
| Rétention `versions/` | **0 = jamais purgé** (`VERSIONS_RETENTION_DAYS` dans le script) |
| Alerte | `msmtp` vers `holluxpanda@gmail.com`, cooldown 12 h, état : `~/.local/state/backup-offsite/last_alert_weather` |

### Côté génération des dumps (`docker-compose.prod.yaml` du dépôt)

```yaml
  backup:
    image: fradelg/mysql-cron-backup
    environment:
      - MYSQL_HOST=db
      - MYSQL_USER=${MYSQL_USER}
      - MYSQL_PASS=${MYSQL_PASSWORD}
      - MYSQL_DB=${MYSQL_DATABASE}
      - CRON_TIME=0 3 * * *          # 03:00 UTC ≈ 05:00 local
      - MAX_BACKUPS=7
      - GZIP_LEVEL=6
    volumes:
      - ./backups:/backup
```

---

## 3. Commandes du quotidien

```bash
rclone ls   gdrive_weather:daily
rclone tree gdrive_weather:
rclone lsd  gdrive_weather:versions
tail ~/scripts/backup-offsite/sync-weather.log        # doit finir par "OK — N dump(s)…"
~/scripts/backup-offsite/sync-weather.sh              # forcer une synchro
rclone size gdrive_weather:
```

---

## 4. Restaurer un backup dans la base

```bash
# 1. récupérer un dump (déjà en clair, aucun déchiffrement)
rclone ls gdrive_weather:daily
rclone copy gdrive_weather:daily/202609040300.db_meteo.sql.gz ~/
#   ou un plus ancien :
#   rclone copy "gdrive_weather:versions/20260904/202608290300.db_meteo.sql.gz" ~/

# 2. vérifier
gunzip -t ~/202609040300.db_meteo.sql.gz && echo OK

# 3. réinjecter (stack prod démarrée)
cd ~/web/weather
gunzip -c ~/202609040300.db_meteo.sql.gz \
  | docker compose -f docker-compose.prod.yaml exec -T db \
      mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"
```

> Depuis l'interface web de Drive : le fichier est directement téléchargeable et
> exploitable (`.sql.gz` standard), pas besoin de rclone pour ce cas-là.

---

## 5. Serveur perdu — reconstruction

### Option A — copie de `rclone.conf` conservée (le plus rapide)

```bash
# installer rclone
curl -O https://downloads.rclone.org/rclone-current-linux-amd64.zip
unzip rclone-current-linux-amd64.zip
install -m 0755 rclone-v*/rclone ~/.local/bin/rclone

# restaurer la config
mkdir -p ~/.config/rclone
cp /chemin/vers/copie/rclone.conf ~/.config/rclone/rclone.conf
chmod 600 ~/.config/rclone/rclone.conf

# si le jeton a expiré :
rclone config reconnect gdrive_weather:      # -> autorisation Google, une fois

rclone copy gdrive_weather:daily/ ~/restore/
```

### Option B — repartir de zéro (rien conservé sauf ce fichier)

```bash
# installer rclone (voir Option A)

rclone config create gdrive_weather drive \
  scope=drive.file \
  root_folder_id=1E1oxCbHtazpz1jk-LFXRGIQ31EJHRv1M
rclone config reconnect gdrive_weather:      # -> autorisation Google dans un navigateur
#   depuis une machine sans navigateur : "rclone authorize \"drive\"" ailleurs, coller le jeton
#   client_id / client_secret : laisser VIDES (client intégré de rclone)

rclone tree gdrive_weather:                   # doit afficher daily/ + versions/
rclone copy gdrive_weather:daily/ ~/restore/
```

### Variante — le dossier a juste été déplacé/renommé dans Drive

`root_folder_id` référence le dossier par **ID**, pas par chemin : un
déplacement ou un renommage dans l'interface web ne casse rien. Si l'ID lui-même
est perdu, ouvrir le dossier dans Drive et le relire dans l'URL
(`.../folders/<ID>`), puis :
`rclone config update gdrive_weather root_folder_id=<ID>`.

### Remettre en place la génération des dumps

Le dépôt `weather` (GitHub `Hollux/weather`) contient tout (`docker-compose.prod.yaml`,
`Makefile`…). Après `git clone` + `.env` + démarrage de la stack prod, le
conteneur `backup` reprend les dumps quotidiens. Reste à réinstaller
`~/scripts/backup-offsite/sync-weather.sh` + la ligne cron (§2), ou à recopier
`~/scripts/backup-offsite/` depuis une sauvegarde.

---

## 6. À garder hors du serveur

1. Une copie de **`~/.config/rclone/rclone.conf`** → clé USB / autre machine
   (reconstruction quasi instantanée). Ce fichier n'a pas de secret critique
   pour weather (pas de chiffrement), mais contient le jeton OAuth Google.
2. Une copie du **binaire `rclone`** (ou au moins la version : `v1.75.0`).
3. Ce fichier, à jour, dans Google Drive.

> Pas de mot de passe de chiffrement à retenir : les backups weather sont en clair.

---

## 7. Point de maintenance connu

rclone utilise son **`client_id` Google partagé**, retiré par Google « courant
2026 ». Quand la synchro échouera avec une erreur d'auth :

1. Créer un client_id OAuth perso : <https://rclone.org/drive/#making-your-own-client-id>
2. L'appliquer aux deux remotes (weather **et** timeline, même compte) :
   ```bash
   rclone config update gdrive_hollux  client_id='...' client_secret='...'
   rclone config update gdrive_weather client_id='...' client_secret='...'
   rclone config reconnect gdrive_hollux:
   rclone config reconnect gdrive_weather:
   ```
