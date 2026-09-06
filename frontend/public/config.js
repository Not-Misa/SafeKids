(function configureSafeKids(windowObject) {
  const localHosts = new Set(['localhost', '127.0.0.1']);
  const isLocalDevelopment = localHosts.has(windowObject.location.hostname);
  const apiBaseUrl = isLocalDevelopment
    ? 'http://localhost/SafeKids-api/api/'
    : `${windowObject.location.origin}/SafeKids-api/api/`;

  windowObject.__SAFEKIDS_CONFIG__ = {
    apiBaseUrl,
  };
})(window);
