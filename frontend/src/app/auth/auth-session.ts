export const SAFE_KIDS_TOKEN_KEY = 'safekids_token';
export const SAFE_KIDS_TOKEN_EXPIRY_KEY = 'safekids_token_expira_en';
export const SAFE_KIDS_USER_KEY = 'safekids_usuario';

export function getStoredUser(): any | null {
  const storedUser = localStorage.getItem(SAFE_KIDS_USER_KEY);
  if (!storedUser) {
    return null;
  }

  try {
    return JSON.parse(storedUser);
  } catch {
    return null;
  }
}

export function hasActiveSession(): boolean {
  const token = localStorage.getItem(SAFE_KIDS_TOKEN_KEY);
  const expiry = localStorage.getItem(SAFE_KIDS_TOKEN_EXPIRY_KEY);

  if (!token || !getStoredUser()) {
    return false;
  }

  if (!expiry) {
    return true;
  }

  const expiryTime = new Date(expiry.replace(' ', 'T')).getTime();
  return Number.isNaN(expiryTime) || expiryTime > Date.now();
}

export function clearSafeKidsSession(): void {
  localStorage.removeItem(SAFE_KIDS_TOKEN_KEY);
  localStorage.removeItem(SAFE_KIDS_TOKEN_EXPIRY_KEY);
  localStorage.removeItem(SAFE_KIDS_USER_KEY);
  localStorage.removeItem('id_usuario');
  localStorage.removeItem('id_guarderia');
  localStorage.removeItem('id_empleado');
  localStorage.removeItem('rol');
}
