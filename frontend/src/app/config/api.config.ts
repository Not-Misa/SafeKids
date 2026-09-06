export interface SafeKidsRuntimeConfig {
  apiBaseUrl?: string;
}

declare global {
  var __SAFEKIDS_CONFIG__: SafeKidsRuntimeConfig | undefined;
}

export const LEGACY_API_BASE_URL = 'http://localhost/SafeKids-api/api/';

function normalizedBaseUrl(value: string): string {
  const trimmedValue = value.trim();
  return trimmedValue.endsWith('/') ? trimmedValue : `${trimmedValue}/`;
}

export function getSafeKidsApiBaseUrl(
  config: SafeKidsRuntimeConfig | undefined = globalThis.__SAFEKIDS_CONFIG__,
): string {
  const configuredUrl = config?.apiBaseUrl;

  return configuredUrl ? normalizedBaseUrl(configuredUrl) : LEGACY_API_BASE_URL;
}

export const SAFEKIDS_API_BASE_URL = getSafeKidsApiBaseUrl();

export function rewriteSafeKidsApiUrl(
  requestUrl: string,
  apiBaseUrl = SAFEKIDS_API_BASE_URL,
): string {
  if (!requestUrl.startsWith(LEGACY_API_BASE_URL)) {
    return requestUrl;
  }

  return `${normalizedBaseUrl(apiBaseUrl)}${requestUrl.slice(LEGACY_API_BASE_URL.length)}`;
}

export function isSafeKidsApiUrl(requestUrl: string, apiBaseUrl = SAFEKIDS_API_BASE_URL): boolean {
  return (
    requestUrl.startsWith(LEGACY_API_BASE_URL) ||
    requestUrl.startsWith(normalizedBaseUrl(apiBaseUrl))
  );
}
