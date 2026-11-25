import { type Client, type ClientOptions } from 'openapi-fetch';
import type { components, paths } from '../src/schema.js';
export type { components, paths } from '../src/schema.js';
/** A typed fetch client for Keen's JSON API. */
export type KeenClient = Client<paths>;
/** An audit entry, as `keen.entries.*` answer it. */
export type AuditEntry = components['schemas']['AuditEntryResource'];
/** A package whose history the caller may read, as `keen.packages.index` lists it. */
export type AuditedPackage = components['schemas']['AuditedPackageResource'];
/**
 * Create a client for Keen's JSON API.
 *
 * `baseUrl` is the host's `keen.routes.prefix` URL, for example
 * `https://app.test/api/keen`: the client assumes no prefix.
 */
export declare function createKeenClient(options: ClientOptions & {
    baseUrl: string;
}): KeenClient;
