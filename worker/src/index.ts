const REPO = `glhd/clave`;
const GITHUB_URL = `https://github.com/${ REPO }`;

const CLI_USER_AGENTS = [
	'curl/',
	'Wget/',
	'HTTPie/',
	'fetch/',
	'undici/',
];

const BOT_USER_AGENTS = [
	'Discordbot',
	'Twitterbot',
	'facebookexternalhit',
	'LinkedInBot',
	'Slackbot',
	'TelegramBot',
	'WhatsApp',
	'Bluesky',
	'Mastodon',
	'Pleroma',
	'Misskey',
	'bot',
	'crawler',
	'spider',
	'preview',
];

const OG_HTML = `<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta property="og:title" content="Clave">
<meta property="og:description" content="Ephemeral Ubuntu VMs for isolated Claude Code sessions.">
<meta property="og:image" content="https://clave.run/og-image.png">
<meta property="og:url" content="https://clave.run">
<meta property="og:type" content="website">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="Clave">
<meta name="twitter:description" content="Ephemeral Ubuntu VMs for isolated Claude Code sessions.">
<meta name="twitter:image" content="https://clave.run/og-image.png">
<meta http-equiv="refresh" content="0;url=https://github.com/${REPO}">
</head>
<body><script>window.location.href="https://github.com/${REPO}";</script></body>
</html>`;

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
  if ! command -v php >/dev/null 2>&1; then
    echo "Error: PHP is required but not installed." >&2
    echo "Install PHP 8.3+ and try again." >&2
    exit 1
  fi

  if [ -d "$BIN_DIR" ] && [ ! -w "$BIN_DIR" ]; then
    SUDO="sudo"
    echo "Note: $BIN_DIR is not writable. You may be prompted for your password." >&2
  fi
}

_install() {
  if [ -f "$BIN_DIR/$BIN_NAME" ]; then
    echo "Upgrading: $BIN_DIR/$BIN_NAME" >&2
  else
    echo "Installing: $BIN_DIR/$BIN_NAME" >&2
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
			return new Response(SCRIPT.trimStart(), {
				headers: {'content-type': 'text/x-sh; charset=utf-8'},
			});
		}

		const ua_lower = ua.toLowerCase();
		if (BOT_USER_AGENTS.some((bot) => ua_lower.includes(bot.toLowerCase()))) {
			return new Response(OG_HTML, {
				headers: {'content-type': 'text/html; charset=utf-8'},
			});
		}

		return Response.redirect(GITHUB_URL, 302);
	},
} satisfies ExportedHandler<Env>;
