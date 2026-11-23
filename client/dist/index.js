import createClient from 'openapi-fetch';
/**
 * Create a client for Keen's JSON API.
 *
 * `baseUrl` is the host's `keen.routes.prefix` URL, for example
 * `https://app.test/api/keen`: the client assumes no prefix.
 */
export function createKeenClient(options) {
    return createClient(options);
}
