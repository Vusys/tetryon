#!/usr/bin/env bash
#
# Fetch a pinned set of built TodoMVC example apps into tests/Fixtures/todomvc/
# (gitignored). This is the foundation for the TodoMVC cross-framework
# compatibility suite: one identical app, ten genuinely different DOM outputs.
#
# No submodule, no vendored bundles, no npm build — a blobless sparse checkout
# of a pinned upstream SHA. Refreshing upstream is a reviewable one-line diff
# (bump TODOMVC_SHA below).
#
# Usage: scripts/fetch-todomvc.sh   (or: composer todomvc:fetch)

set -euo pipefail

# "Revise README for 2.0.0" — https://github.com/tastejs/todomvc
TODOMVC_SHA="ff43b02e59dfa604386bb382034b2cd07c2bcd8a"

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DEST="${REPO_ROOT}/tests/Fixtures/todomvc"

# The built output we serve. Every modern example commits its dist/; the legacy
# ones commit node_modules/ — this is exactly how todomvc.com serves them, so no
# build step is required for any of the ten.
SPARSE_PATHS=(
  /examples/react/dist
  /examples/react-redux/dist
  /examples/vue/dist
  /examples/svelte/dist
  /examples/preact/dist
  /examples/lit/dist
  /examples/angular/dist
  /examples/jquery/dist
  /examples/backbone/dist
  /examples/javascript-es6/dist
)

if [ -d "${DEST}/.git" ]; then
  echo "TodoMVC fixtures already present at ${DEST}."
  echo "Delete the directory and re-run to refresh, or bump TODOMVC_SHA."
  exit 0
fi

echo "Fetching TodoMVC examples at ${TODOMVC_SHA} into ${DEST} ..."
rm -rf "${DEST}"
mkdir -p "$(dirname "${DEST}")"

git clone --filter=blob:none --no-checkout https://github.com/tastejs/todomvc.git "${DEST}"
git -C "${DEST}" sparse-checkout set --no-cone "${SPARSE_PATHS[@]}"
git -C "${DEST}" checkout "${TODOMVC_SHA}"

echo "Done. TodoMVC apps are under ${DEST}/examples/<name>/dist/"
echo "(angular builds to examples/angular/dist/browser/)"
