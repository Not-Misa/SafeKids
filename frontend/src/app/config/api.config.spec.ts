import { describe, expect, it } from 'vitest';
import {
  LEGACY_API_BASE_URL,
  getSafeKidsApiBaseUrl,
  isSafeKidsApiUrl,
  rewriteSafeKidsApiUrl,
} from './api.config';

describe('SafeKids API runtime configuration', () => {
  it('normalizes the configured base URL', () => {
    expect(
      getSafeKidsApiBaseUrl({
        apiBaseUrl: 'https://safekids.example/api',
      }),
    ).toBe('https://safekids.example/api/');
  });

  it('rewrites legacy localhost endpoints for deployment', () => {
    expect(
      rewriteSafeKidsApiUrl(
        `${LEGACY_API_BASE_URL}login.php`,
        'https://safekids.example/SafeKids-api/api/',
      ),
    ).toBe('https://safekids.example/SafeKids-api/api/login.php');
  });

  it('leaves external URLs unchanged', () => {
    expect(
      rewriteSafeKidsApiUrl(
        'https://res.cloudinary.com/example/image.jpg',
        'https://safekids.example/SafeKids-api/api/',
      ),
    ).toBe('https://res.cloudinary.com/example/image.jpg');
  });

  it('recognizes legacy and configured API requests', () => {
    const configuredBaseUrl =
      'https://safekids.example/SafeKids-api/api/';

    expect(
      isSafeKidsApiUrl(
        `${LEGACY_API_BASE_URL}listar_ninos.php`,
        configuredBaseUrl,
      ),
    ).toBe(true);
    expect(
      isSafeKidsApiUrl(
        `${configuredBaseUrl}listar_ninos.php`,
        configuredBaseUrl,
      ),
    ).toBe(true);
  });
});
