import installScript from './install.sh';

const GITHUB_URL = 'https://github.com/glhd/clave';

const CLI_USER_AGENTS = [
	'curl/',
	'Wget/',
	'HTTPie/',
	'fetch/',
	'undici/',
];

export default {
	async fetch(request: Request): Promise<Response> {
		const ua = request.headers.get('user-agent') || '';

		if (CLI_USER_AGENTS.some((prefix) => ua.includes(prefix))) {
			return new Response(installScript, {
				headers: { 'content-type': 'text/plain; charset=utf-8' },
			});
		}

		return Response.redirect(GITHUB_URL, 302);
	},
};
