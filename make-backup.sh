#!/bin/bash

# === SETTINGS ===
MAX_BACKUPS=5          # Number of backups to keep
TAG_PREFIX="backup-"   # Tag prefix
REMOTE_NAME="origin"   # Remote repository name

# === CHECK FOR UNCOMMITTED CHANGES ===
if [[ -n $(git status --porcelain) ]]; then
  echo "⚠️  У вас есть незакоммиченные изменения."
  read -p "Хотите закоммитить их перед созданием бэкапа? (y/n): " answer

  if [[ "$answer" == "y" || "$answer" == "Y" ]]; then
    read -p "Введите комментарий к коммиту: " commit_msg
    git add .
    git commit -m "$commit_msg"
    echo "✅ Изменения закоммичены."
  else
    echo "❌ Бэкап прерван: необходимо чистое состояние или коммит."
    exit 1
  fi
fi

# === CREATE TAG ===
timestamp=$(date +"%Y%m%d-%H%M")
tag_name="${TAG_PREFIX}${timestamp}"

read -p "Введите комментарий для бэкапа (тега): " tag_msg

git tag -a "$tag_name" -m "$tag_msg"
git push "$REMOTE_NAME" "$tag_name"

echo "✅ Бэкап сохранён как тег: $tag_name"

# === CLEANUP OLD TAGS ===
echo "🧹 Проверка на количество старых бэкапов..."

# Get list of all tags with prefix, sorted by date
old_tags=$(git tag --sort=-creatordate | grep "^$TAG_PREFIX" | tail -n +$((MAX_BACKUPS + 1)))

if [[ -n "$old_tags" ]]; then
  echo "Удаляются старые теги:"
  echo "$old_tags"

  # Delete locally and remotely
  for tag in $old_tags; do
    git tag -d "$tag"
    git push "$REMOTE_NAME" --delete "$tag"
  done

  echo "✅ Удалены старые теги, оставлено $MAX_BACKUPS последних бэкапов."
else
  echo "👌 Нет старых бэкапов для удаления."
fi
