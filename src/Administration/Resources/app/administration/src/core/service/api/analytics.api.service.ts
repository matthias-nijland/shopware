import type { AxiosInstance } from 'axios';
import type { LoginService } from '../login.service';
import ApiService from '../api.service';

type AnalyticsToken = {
    token: string;
    expiresAt: number;
};

/**
 * @private
 */
export default class AnalyticsApiService extends ApiService {
    private token: AnalyticsToken | null = null;

    private pendingTokenFetch: Promise<AnalyticsToken> | null = null;

    constructor(httpClient: AxiosInstance, loginService: LoginService, apiEndpoint = 'analytics') {
        super(httpClient, loginService, apiEndpoint, 'application/json');

        this.name = 'analyticsService';
    }

    public async getToken(): Promise<AnalyticsToken> {
        if (this.token !== null && this.isTokenValid(this.token.expiresAt)) {
            return this.token;
        }

        if (this.pendingTokenFetch !== null) {
            return this.pendingTokenFetch;
        }

        this.pendingTokenFetch = this.fetchToken();

        try {
            this.token = await this.pendingTokenFetch;
            return this.token;
        } finally {
            this.pendingTokenFetch = null;
        }
    }

    private async fetchToken(): Promise<AnalyticsToken> {
        const { data } = await this.httpClient.get<AnalyticsToken>(`/${this.getApiBasePath()}/token`, {
            headers: this.getBasicHeaders(),
        });

        return data;
    }

    private isTokenValid(expiryTimestamp: number) {
        const currentTime = Math.floor(Date.now() / 1000);
        return currentTime < expiryTimestamp;
    }
}

/**
 * @private
 * @sw-package data-services
 */
export type { AnalyticsApiService, AnalyticsToken };
