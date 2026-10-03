/**
 * Requests to the plugin's REST routes, with the nonce the settings page
 * prints in window.persianKitSettings.
 */

/**
 * An error from the REST API, with its HTTP status, code and data.
 */
export class RestError extends Error {
    constructor(message, status = 0, code = '', data = null) {
        super(message);
        this.name = 'RestError';
        this.status = status;
        this.code = code;
        this.data = data;
    }
}

/**
 * @param {string} endpoint Route under persian-kit/v1/, such as 'normalize/status'.
 * @param {string} method   GET or POST.
 * @param {Object} params   Query parameters for GET (arrays as key[]), the JSON body otherwise.
 * @returns {Promise<any>}
 */
export async function restFetch(endpoint, method = 'GET', params = {}) {
    const settings = window.persianKitSettings;
    let url = settings.restUrl + endpoint;
    const options = {
        method,
        headers: {
            'X-WP-Nonce': settings.nonce,
        },
    };

    if (method === 'GET') {
        const query = new URLSearchParams();
        for (const [key, value] of Object.entries(params)) {
            if (Array.isArray(value)) {
                value.forEach((item) => query.append(`${key}[]`, item));
            } else {
                query.append(key, value);
            }
        }
        const queryString = query.toString();
        if (queryString !== '') {
            // Plain permalinks put the route in ?rest_route=.
            url += (url.includes('?') ? '&' : '?') + queryString;
        }
    } else {
        options.headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(params);
    }

    let response;
    try {
        response = await fetch(url, options);
    } catch (e) {
        throw new RestError(e.message, 0, 'network_error');
    }

    if (!response.ok) {
        const body = await response.json().catch(() => null);
        throw new RestError(
            (body && body.message) || response.statusText,
            response.status,
            (body && body.code) || '',
            (body && body.data) || null
        );
    }

    return response.json().catch(() => {
        // A security plugin or proxy answered instead of WordPress.
        throw new RestError(response.statusText || 'Invalid response', response.status, 'invalid_json');
    });
}
