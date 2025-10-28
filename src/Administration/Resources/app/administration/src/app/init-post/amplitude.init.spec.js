import initAmplitude from './amplitude.init';
import { TelemetryEvent } from '../../core/telemetry/types';

const mockAmplitudeInstance = {
    add: jest.fn(),
    init: jest.fn().mockReturnValue({ promise: Promise.resolve() }),
    track: jest.fn(),
    setUserId: jest.fn(),
    getUserId: jest.fn(),
    flush: jest.fn(),
    reset: jest.fn(),
    config: {},
};

jest.mock('@amplitude/analytics-browser', () => ({
    AmplitudeBrowser: jest.fn(() => mockAmplitudeInstance),
}));

jest.mock('@amplitude/analytics-client-common', () => ({
    FetchTransport: class FetchTransport {
        buildResponse(data) {
            return data;
        }
    },
}));

describe('src/app/post-init/amplitude.init.ts', () => {
    let mockAnalyticsService;

    beforeEach(() => {
        Object.keys(mockAmplitudeInstance).forEach((key) => {
            if (typeof mockAmplitudeInstance[key]?.mockClear === 'function') {
                mockAmplitudeInstance[key].mockClear();
            }
        });
        mockAmplitudeInstance.init.mockReturnValue({ promise: Promise.resolve() });

        mockAnalyticsService = {
            getToken: jest.fn().mockResolvedValue({ token: 'test-token', expiresAt: Date.now() + 3600 }),
        };

        Shopware.Service = jest.fn((serviceName) => {
            if (serviceName === 'analyticsService') {
                return mockAnalyticsService;
            }
            return undefined;
        });

        global.Shopware = {
            ...global.Shopware,
            Service: Shopware.Service,
            Context: {
                ...global.Shopware?.Context,
                app: {
                    systemCurrencyISOCode: 'EUR',
                },
            },
        };

        Shopware.Store.get('context').app.analyticsGatewayUrl = 'https://analytics.example.com';

        global.repositoryFactoryMock.responses.addResponse({
            method: 'Post',
            url: '/search/language',
            status: 200,
            response: {
                data: [
                    {
                        id: 'language-id',
                        attributes: {
                            name: 'English',
                        },
                    },
                ],
            },
        });
    });

    describe('initialization', () => {
        it('add enrichment plugin and calls initialization routine', async () => {
            await initAmplitude();

            expect(mockAmplitudeInstance.add).toHaveBeenCalled();
            expect(mockAmplitudeInstance.add).toHaveBeenCalledWith(
                expect.objectContaining({
                    name: 'DefaultShopwareProperties',
                    execute: expect.any(Function),
                }),
            );

            expect(mockAmplitudeInstance.init).toHaveBeenCalled();
            expect(mockAmplitudeInstance.init).toHaveBeenCalledWith(
                'placeholder-apikey',
                undefined,
                expect.objectContaining({
                    autocapture: false,
                    serverZone: 'EU',
                    appVersion: Shopware.Store.get('context').app.config.version,
                    trackingOptions: {
                        ipAddress: false,
                        language: false,
                        platform: false,
                    },
                    fetchRemoteConfig: false,
                }),
            );
        });

        it('should return early when analyticsGatewayUrl is not set', async () => {
            Shopware.Store.get('context').app.analyticsGatewayUrl = null;

            await initAmplitude();

            expect(mockAmplitudeInstance.init).not.toHaveBeenCalled();
        });

        it('should execute enrichment plugin with route properties when router is available', async () => {
            Object.defineProperty(window.screen, 'orientation', {
                value: { type: 'landscape-primary' },
                configurable: true,
            });

            const mockRoute = {
                value: {
                    name: 'sw.product.detail',
                    path: '/sw/product/detail/123',
                    fullPath: '/sw/product/detail/123?tab=general',
                },
            };

            Shopware.Application.view = {
                router: {
                    currentRoute: mockRoute,
                },
            };

            await initAmplitude();

            const enrichmentPlugin = mockAmplitudeInstance.add.mock.calls[0][0];
            const mockEvent = { event_properties: {} };
            const result = await enrichmentPlugin.execute(mockEvent);

            expect(result.event_properties).toEqual(
                expect.objectContaining({
                    sw_page_name: 'sw.product.detail',
                    sw_page_path: '/sw/product/detail/123',
                    sw_page_full_path: '/sw/product/detail/123?tab=general',
                    sw_screen_orientation: 'landscape',
                }),
            );
        });

        it('should execute enrichment plugin without route properties when router is not available', async () => {
            Object.defineProperty(window.screen, 'orientation', {
                value: { type: 'portrait-primary' },
                configurable: true,
            });

            Shopware.Application.view = null;

            await initAmplitude();

            const enrichmentPlugin = mockAmplitudeInstance.add.mock.calls[0][0];
            const mockEvent = { event_properties: {} };
            const result = await enrichmentPlugin.execute(mockEvent);

            expect(result.event_properties.sw_page_name).toBeUndefined();
            expect(result.event_properties.sw_page_path).toBeUndefined();
            expect(result.event_properties.sw_page_full_path).toBeUndefined();
        });
    });

    describe('AuthenticatedFetchTransport', () => {
        let originalFetch;

        beforeEach(() => {
            originalFetch = global.fetch;
        });

        afterEach(() => {
            global.fetch = originalFetch;
        });

        it('should send request with auth header when token is available', async () => {
            const mockResponse = {
                ok: true,
                status: 200,
                text: jest.fn().mockResolvedValue('{"code": 200}'),
            };
            global.fetch = jest.fn().mockResolvedValue(mockResponse);

            await initAmplitude();

            const transport = mockAmplitudeInstance.config.transportProvider;
            await transport.send('https://analytics.example.com/event', { events: [] });

            expect(global.fetch).toHaveBeenCalledWith(
                'https://analytics.example.com/event',
                expect.objectContaining({
                    method: 'POST',
                    headers: expect.objectContaining({
                        'Content-Type': 'application/json',
                        Accept: '*/*',
                        Authorization: 'Bearer test-token',
                    }),
                }),
            );
        });

        it('should return 401 response when token fetch fails', async () => {
            mockAnalyticsService.getToken.mockRejectedValue(new Error('Token unavailable'));

            global.fetch = jest.fn();

            await initAmplitude();

            const transport = mockAmplitudeInstance.config.transportProvider;
            const result = await transport.send('https://analytics.example.com/event', { events: [] });

            expect(global.fetch).not.toHaveBeenCalled();
            expect(result).toEqual({ code: 401, message: 'Auth token unavailable' });
        });

        it('should handle non-JSON response gracefully', async () => {
            const mockResponse = {
                ok: true,
                status: 200,
                text: jest.fn().mockResolvedValue('not-json'),
            };
            global.fetch = jest.fn().mockResolvedValue(mockResponse);

            await initAmplitude();

            const transport = mockAmplitudeInstance.config.transportProvider;
            const result = await transport.send('https://analytics.example.com/event', { events: [] });

            expect(result).toEqual({ code: 200 });
        });

        it('should throw error when fetch is undefined', async () => {
            global.fetch = undefined;

            await initAmplitude();

            const transport = mockAmplitudeInstance.config.transportProvider;

            await expect(transport.send('https://analytics.example.com/event', { events: [] })).rejects.toThrow(
                'FetchTransport is not supported',
            );
        });

        it('should return network error response when fetch throws', async () => {
            global.fetch = jest.fn().mockRejectedValue(new Error('Network error'));

            await initAmplitude();

            const transport = mockAmplitudeInstance.config.transportProvider;
            const result = await transport.send('https://analytics.example.com/event', { events: [] });

            expect(result).toEqual({ code: 0, message: 'Network error' });
        });
    });

    describe('event handling', () => {
        it.each([
            [
                new TelemetryEvent('page_change', {
                    from: { name: 'sw.dashboard.index', path: '/sw/dashboard/index' },
                    to: {
                        name: 'sw.product.index',
                        path: '/sw/product/index',
                        fullPath: '/sw-product/index?order=asc&page=1&limit=50',
                    },
                }),
                {
                    eventName: 'Page Viewed',
                    properties: {
                        sw_route_from_name: 'sw.dashboard.index',
                        sw_route_from_href: '/sw/dashboard/index',
                        sw_route_to_name: 'sw.product.index',
                        sw_route_to_href: '/sw/product/index',
                        sw_route_to_query: 'order=asc&page=1&limit=50',
                    },
                },
            ],
            [
                new TelemetryEvent('user_interaction', {
                    target: (() => {
                        const fakeButton = document.createElement('button');
                        fakeButton.innerText = 'Save';
                        fakeButton.setAttribute('data-analytics-id', 'administration.sw-product.save');
                        fakeButton.setAttribute('data-analytics-product-name', 'nice product');

                        return fakeButton;
                    })(),
                    originalEvent: new MouseEvent('click', {
                        clientX: 150,
                        clientY: 75,
                        button: 2,
                    }),
                }),
                {
                    eventName: 'Button Click',
                    properties: {
                        sw_element_id: 'administration.sw-product.save',
                        sw_element_product_name: 'nice product',
                        sw_pointer_x: 150,
                        sw_pointer_y: 75,
                        sw_pointer_button: 0,
                    },
                },
            ],
            [
                new TelemetryEvent('user_interaction', {
                    target: (() => {
                        const fakeLink = document.createElement('a');
                        fakeLink.innerText = 'Read more';
                        fakeLink.setAttribute('href', 'https://example.com');
                        fakeLink.setAttribute('target', '_blank');

                        return fakeLink;
                    })(),
                    originalEvent: new Event('click'),
                }),
                {
                    eventName: 'Link Visited',
                    properties: {
                        sw_link_href: 'https://example.com',
                        sw_link_type: 'external',
                    },
                },
            ],
        ])('handles event', async (telemetryEvent, trackedData) => {
            await initAmplitude();

            Shopware.Utils.EventBus.emit('telemetry', telemetryEvent);

            expect(mockAmplitudeInstance.track).toHaveBeenCalled();
            expect(mockAmplitudeInstance.track).toHaveBeenCalledWith(trackedData.eventName, trackedData.properties);
        });
    });

    describe('user identification', () => {
        const testShopId = 'knneBsx7LiKySnUq';
        const testUserId = '8b8ebef4-7fa3-4844-ab7e-120463ea558b';

        beforeEach(() => {
            jest.clearAllMocks();

            Shopware.Store.get('context').app.config.shopId = testShopId;
        });

        it('should set user ID in format "shopId:userId"', async () => {
            await initAmplitude();

            const identifyEvent = new TelemetryEvent('identify', {
                userId: testUserId,
            });

            Shopware.Utils.EventBus.emit('telemetry', identifyEvent);

            expect(mockAmplitudeInstance.setUserId).toHaveBeenCalledWith(`${testShopId}:${testUserId}`);
        });

        it('should update user ID when a different user identifies', async () => {
            await initAmplitude();

            const firstIdentifyEvent = new TelemetryEvent('identify', {
                userId: testUserId,
            });

            Shopware.Utils.EventBus.emit('telemetry', firstIdentifyEvent);

            expect(mockAmplitudeInstance.setUserId).toHaveBeenCalledWith(`${testShopId}:${testUserId}`);

            mockAmplitudeInstance.setUserId.mockClear();

            const anotherUserId = '48dad3c3-89b9-47a1-bf67-a1cd6fc68952';
            const secondIdentifyEvent = new TelemetryEvent('identify', {
                userId: anotherUserId,
            });

            Shopware.Utils.EventBus.emit('telemetry', secondIdentifyEvent);

            expect(mockAmplitudeInstance.setUserId).toHaveBeenCalledWith(`${testShopId}:${anotherUserId}`);
        });
    });

    describe('login and logout tracking', () => {
        const testShopId = 'knneBsx7LiKySnUq';

        beforeEach(() => {
            jest.clearAllMocks();

            Shopware.Store.get('context').app.config.shopId = testShopId;
        });

        it('should track Login event when a identify telemetry event with a different userId arrives', async () => {
            let amplitudeUserId = null;
            mockAmplitudeInstance.setUserId.mockImplementation((userId) => {
                amplitudeUserId = userId;
            });
            mockAmplitudeInstance.getUserId.mockImplementation(() => amplitudeUserId);

            await initAmplitude();

            let newUserId = 'newUserId-1';
            Shopware.Utils.EventBus.emit(
                'telemetry',
                new TelemetryEvent('identify', {
                    userId: newUserId,
                }),
            );
            expect(mockAmplitudeInstance.track).toHaveBeenCalledWith('Login');

            newUserId = 'newUserId-2';
            Shopware.Utils.EventBus.emit(
                'telemetry',
                new TelemetryEvent('identify', {
                    userId: newUserId,
                }),
            );
            expect(mockAmplitudeInstance.track).toHaveBeenCalledWith('Login');

            const sameUserId = newUserId;
            Shopware.Utils.EventBus.emit(
                'telemetry',
                new TelemetryEvent('identify', {
                    userId: sameUserId,
                }),
            );

            expect(mockAmplitudeInstance.track).toHaveBeenCalledTimes(2);
        });

        it('should track Logout event when a reset telemetry event arrives', async () => {
            await initAmplitude();

            const resetEvent = new TelemetryEvent('reset', {});

            Shopware.Utils.EventBus.emit('telemetry', resetEvent);

            expect(mockAmplitudeInstance.track).toHaveBeenCalledWith('Logout');
        });

        it('should call flush and reset after Logout event', async () => {
            jest.useFakeTimers();

            await initAmplitude();

            const resetEvent = new TelemetryEvent('reset', {});

            Shopware.Utils.EventBus.emit('telemetry', resetEvent);

            expect(mockAmplitudeInstance.flush).not.toHaveBeenCalled();
            expect(mockAmplitudeInstance.reset).not.toHaveBeenCalled();

            jest.runAllTimers();

            expect(mockAmplitudeInstance.flush).toHaveBeenCalled();
            expect(mockAmplitudeInstance.reset).toHaveBeenCalled();

            jest.useRealTimers();
        });
    });
});
