/**
 * @sw-package data-services
 */
import MockAdapter from 'axios-mock-adapter';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import AnalyticsApiService from 'src/core/service/api/analytics.api.service';

function getAnalyticsService(client) {
    return new AnalyticsApiService(client, createLoginService(client, Shopware.Context.api));
}

describe('analyticsService', () => {
    it('has the correct name', async () => {
        const analyticsApiService = getAnalyticsService(createHTTPClient());

        expect(analyticsApiService.name).toBe('analyticsService');
    });

    it('gets a token from the api', async () => {
        const client = createHTTPClient();
        const mockAdapter = new MockAdapter(client);
        const analyticsApi = getAnalyticsService(client);

        const mockToken = {
            token: 'test-token',
            expiresAt: Math.floor(Date.now() / 1000) + 3600,
        };

        mockAdapter.onGet('/api/analytics/token').reply(200, mockToken);

        const response = await analyticsApi.getToken();

        expect(response).toEqual(mockToken);
    });

    it('caches the token and returns cached value on subsequent calls', async () => {
        const client = createHTTPClient();
        const mockAdapter = new MockAdapter(client);
        const analyticsApi = getAnalyticsService(client);

        const mockToken = {
            token: 'test-token',
            expiresAt: Math.floor(Date.now() / 1000) + 3600,
        };

        mockAdapter.onGet('/api/analytics/token').reply(200, mockToken);

        const firstResponse = await analyticsApi.getToken();
        const secondResponse = await analyticsApi.getToken();

        expect(firstResponse).toEqual(mockToken);
        expect(secondResponse).toEqual(mockToken);
        expect(mockAdapter.history.get).toHaveLength(1);
    });

    it('fetches a new token when the cached token is expired', async () => {
        const client = createHTTPClient();
        const mockAdapter = new MockAdapter(client);
        const analyticsApi = getAnalyticsService(client);

        const expiredToken = {
            token: 'expired-token',
            expiresAt: Math.floor(Date.now() / 1000) - 100,
        };

        const newToken = {
            token: 'new-token',
            expiresAt: Math.floor(Date.now() / 1000) + 3600,
        };

        mockAdapter.onGet('/api/analytics/token').replyOnce(200, expiredToken);
        mockAdapter.onGet('/api/analytics/token').replyOnce(200, newToken);

        const firstResponse = await analyticsApi.getToken();
        expect(firstResponse).toEqual(expiredToken);

        const secondResponse = await analyticsApi.getToken();
        expect(secondResponse).toEqual(newToken);
        expect(mockAdapter.history.get).toHaveLength(2);
    });

    it('handles concurrent requests with a single HTTP call', async () => {
        const client = createHTTPClient();
        const mockAdapter = new MockAdapter(client);
        const analyticsApi = getAnalyticsService(client);

        const mockToken = {
            token: 'test-token',
            expiresAt: Math.floor(Date.now() / 1000) + 3600,
        };

        mockAdapter.onGet('/api/analytics/token').reply(200, mockToken);

        const [
            response1,
            response2,
            response3,
        ] = await Promise.all([
            analyticsApi.getToken(),
            analyticsApi.getToken(),
            analyticsApi.getToken(),
        ]);

        expect(response1).toEqual(mockToken);
        expect(response2).toEqual(mockToken);
        expect(response3).toEqual(mockToken);
        expect(mockAdapter.history.get).toHaveLength(1);
    });
});
