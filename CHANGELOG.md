# Changelog

## 1.2.0

- Route address search through the same signed relay/proxy environment as cadastral requests.
- Add bounded address-search connect/request timeouts and stop returning false empty results on transport failure.
- Extract shared RSA relay signing into RelaySigner.

## 1.1.0

- Add RSA-SHA256 signed relay requests for development environments without direct Rosreestr access.
- Keep token relay authentication and ROSREESTR_PROXY for backward compatibility.

## 1.0.3

- Send the relay credential through X-Rosreestr-Relay-Token so it works reliably behind Apache/FastCGI.

## 1.0.0

- First versioned release for use by goskadastr projects.
- PHP 8.2+ support.
- Composer dependencies refreshed to secure compatible releases.
