const REPO = `glhd/clave`;
const GITHUB_URL = `https://github.com/${ REPO }`;

const CLI_USER_AGENTS = [
	'curl/',
	'Wget/',
	'HTTPie/',
	'fetch/',
	'undici/',
];

const SCRIPT = `
#!/bin/sh

set -e

REPO="${ REPO }"
BIN_DIR="/usr/local/bin"
BIN_NAME="clave"
SUDO=""

_main() {
  if [ -f "$BIN_DIR/$BIN_NAME" ] && ! _is_old; then
    echo "$BIN_NAME $($BIN_NAME --version) is already installed" >&2
    return
  fi

  _install
}

_prep() {
  if [ -d "$BIN_DIR" ] && [ ! -w "$BIN_DIR" ]; then
    SUDO="sudo"
  fi
}

_install() {
  if [ -f "$BIN_DIR/$BIN_NAME" ]; then
    echo "upgrading: $BIN_DIR/$BIN_NAME" >&2
  else
    echo "installing: $BIN_DIR/$BIN_NAME" >&2
  fi

  tmpfile="$(mktemp)"
  curl --fail --location --progress-bar \\
    "https://github.com/$REPO/releases/latest/download/clave.phar" \\
    -o "$tmpfile"

  $SUDO mkdir -p "$BIN_DIR"
  $SUDO mv "$tmpfile" "$BIN_DIR/$BIN_NAME"
  $SUDO chmod +x "$BIN_DIR/$BIN_NAME"

  echo "$($BIN_NAME --version) installed" >&2
}

_is_old() {
  command -v "$BIN_NAME" >/dev/null 2>&1 || return 0

  new_version=$(curl -Ssf "https://api.github.com/repos/$REPO/releases/latest" \\
    | grep '"tag_name"' | head -1 | sed 's/.*"v\\?\\([^"]*\\)".*/\\1/')
  old_version=$($BIN_NAME --version 2>/dev/null | grep -oE '[0-9]+\\.[0-9]+\\.[0-9]+' || echo "0.0.0")

  [ "$new_version" != "$old_version" ]
}

_prep
_main "$@"
`;

export default {
	async fetch(request, env, ctx): Promise<Response> {
		const ua = request.headers.get('user-agent') || '';
		
		if (CLI_USER_AGENTS.some((prefix) => ua.includes(prefix))) {
			return new Response(SCRIPT, {
				headers: {'content-type': 'text/plain; charset=utf-8'},
			});
		}
		
		return Response.redirect(GITHUB_URL, 302);
	},
} satisfies ExportedHandler<Env>;
