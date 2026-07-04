#!/usr/bin/env bash
# ============================================================
#  CKMLS — one-command tag & release
#
#  Usage:
#    ./release.sh            # bump patch   (v1.0.0 -> v1.0.1)
#    ./release.sh patch      # same as above
#    ./release.sh minor      # v1.0.0 -> v1.1.0
#    ./release.sh major      # v1.0.0 -> v2.0.0
#    ./release.sh v1.2.3      # explicit version
#    ./release.sh minor -n    # dry run: show what would happen, change nothing
#
#  Safeguards: must be on main, clean tree, and in sync with origin.
# ============================================================
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")"

BUMP="patch"
DRY_RUN=0
for arg in "$@"; do
    case "$arg" in
        -n|--dry-run) DRY_RUN=1 ;;
        patch|minor|major) BUMP="$arg" ;;
        v[0-9]*) BUMP="$arg" ;;   # explicit version like v1.2.3
        *) echo "Unknown argument: $arg" >&2; exit 1 ;;
    esac
done

say()  { printf '\033[1;36m%s\033[0m\n' "$*"; }
ok()   { printf '\033[1;32m%s\033[0m\n' "$*"; }
warn() { printf '\033[1;33m%s\033[0m\n' "$*"; }
die()  { printf '\033[1;31m%s\033[0m\n' "$*" >&2; exit 1; }

# ── Preflight ────────────────────────────────────────────────
command -v gh >/dev/null 2>&1 || die "gh CLI not found — install it or create the release manually."
gh auth status >/dev/null 2>&1 || die "gh is not authenticated — run: gh auth login"

BRANCH="$(git rev-parse --abbrev-ref HEAD)"
[ "$BRANCH" = "main" ] || die "Not on main (on '$BRANCH'). Switch to main before releasing."

# Clean tree (ignore .DS_Store noise)
if [ -n "$(git status --porcelain | grep -vE '\.DS_Store$' || true)" ]; then
    git status --short | grep -vE '\.DS_Store$' || true
    die "Working tree has uncommitted changes. Commit or stash them first."
fi

say "Fetching origin…"
git fetch -q origin
LOCAL="$(git rev-parse HEAD)"
REMOTE="$(git rev-parse origin/main)"
[ "$LOCAL" = "$REMOTE" ] || die "Local main is not in sync with origin/main. Push/pull first."

# ── Determine version ────────────────────────────────────────
LAST_TAG="$(git tag -l 'v*' --sort=-v:refname | head -1)"
[ -z "$LAST_TAG" ] && LAST_TAG=""

if [[ "$BUMP" == v* ]]; then
    NEW_TAG="$BUMP"
else
    if [ -z "$LAST_TAG" ]; then
        NEW_TAG="v1.0.0"
    else
        ver="${LAST_TAG#v}"
        IFS='.' read -r MA MI PA <<< "$ver"
        MA=${MA:-0}; MI=${MI:-0}; PA=${PA:-0}
        case "$BUMP" in
            major) MA=$((MA+1)); MI=0; PA=0 ;;
            minor) MI=$((MI+1)); PA=0 ;;
            patch) PA=$((PA+1)) ;;
        esac
        NEW_TAG="v${MA}.${MI}.${PA}"
    fi
fi

[[ "$NEW_TAG" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]] || die "Bad version '$NEW_TAG' (expected vMAJOR.MINOR.PATCH)."
git rev-parse -q --verify "refs/tags/$NEW_TAG" >/dev/null && die "Tag $NEW_TAG already exists."

# ── Build release notes from commits since last tag ──────────
RANGE="HEAD"
[ -n "$LAST_TAG" ] && RANGE="${LAST_TAG}..HEAD"
# Drop noisy auto-snapshot commits from the changelog.
NOTES="$(git log --pretty='- %s' "$RANGE" | grep -viE 'snapshot backup|^- backup ' || true)"
[ -z "$NOTES" ] && NOTES="- Maintenance release (no notable changes since ${LAST_TAG:-start})."

NOTES_BODY="CKMLS ${NEW_TAG}

Changes since ${LAST_TAG:-first commit}:
${NOTES}"

# ── Summary ──────────────────────────────────────────────────
say "──────────────────────────────────────────────"
say "  Release plan"
say "──────────────────────────────────────────────"
echo "  Previous tag : ${LAST_TAG:-<none>}"
echo "  New version  : ${NEW_TAG}"
echo "  Commit       : $(git rev-parse --short HEAD)"
echo ""
echo "$NOTES_BODY"
say "──────────────────────────────────────────────"

if [ "$DRY_RUN" = "1" ]; then
    warn "Dry run — no tag created, nothing pushed."
    exit 0
fi

read -r -p "Create and publish ${NEW_TAG}? [y/N] " reply
case "$reply" in y|Y|yes|YES) ;; *) die "Aborted." ;; esac

# ── Tag, push, release ───────────────────────────────────────
say "Tagging ${NEW_TAG}…"
git tag -a "$NEW_TAG" -m "$NOTES_BODY"
git push -q origin "$NEW_TAG"
ok "Tag pushed."

say "Creating GitHub release…"
printf '%s\n' "$NOTES_BODY" | gh release create "$NEW_TAG" \
    --title "${NEW_TAG}" \
    --notes-file - \
    --latest
ok "Released ${NEW_TAG}"
gh release view "$NEW_TAG" --json url -q '"→ "+.url'
