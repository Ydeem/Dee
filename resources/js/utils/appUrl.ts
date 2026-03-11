function detectBasePath(): string {
  if (typeof window === 'undefined') {
    return '';
  }

  const pathname = window.location.pathname || '';
  const markers = [
    '/hr/',
    '/dashboard',
    '/login',
    '/register',
    '/forgot-password',
    '/reset-password',
    '/api/'
  ];

  const indexes = markers
    .map((marker) => pathname.indexOf(marker))
    .filter((index) => index >= 0);

  if (!indexes.length) {
    return '';
  }

  return pathname.slice(0, Math.min(...indexes));
}

export function appUrl(path = '/'): string {
  if (!path) {
    return detectBasePath() || '/';
  }

  if (/^(https?:)?\/\//.test(path)) {
    return path;
  }

  const basePath = detectBasePath();
  const normalizedPath = path.startsWith('/') ? path : `/${path}`;

  if (!basePath) {
    return normalizedPath;
  }

  return normalizedPath === '/' ? `${basePath}/` : `${basePath}${normalizedPath}`;
}
