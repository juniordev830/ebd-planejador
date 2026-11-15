#!/usr/bin/env bash
set -euo pipefail

# Installs an optional sparse local checkout without copying the Bible corpus
# into this repository's history. The imported source repo remains untouched.
TRANSLATION="${1:-ACF11}"
if [[ ! "$TRANSLATION" =~ ^[A-Za-z0-9_-]{1,20}$ ]]; then
  echo "Código de tradução inválido." >&2
  exit 2
fi
if ! command -v git >/dev/null 2>&1; then
  echo "Git não encontrado. Instale o Git antes de executar." >&2
  exit 2
fi
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
DEST="$PROJECT_DIR/data/bible-data"
if [[ -e "$DEST" ]]; then
  echo "A base local já existe em $DEST; nenhuma alteração foi feita." >&2
  exit 2
fi

mkdir -p "$PROJECT_DIR/data"
git clone --depth 1 --filter=blob:none --sparse \
  https://github.com/juniordev830/adc-de-fuc-ebd.git "$DEST"
git -C "$DEST" sparse-checkout set "bolls/$TRANSLATION"

echo "Base local preparada: $DEST"
echo "Exemplo:"
echo "php planner.php examples/aula-completa.json --biblia-dir=data/bible-data --traducao=$TRANSLATION --format=markdown"
echo "Confira os direitos de uso e redistribuição da tradução antes de publicar textos."
