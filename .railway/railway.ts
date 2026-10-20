import {
    defineRailway,
    github,
    preserve,
    project,
    service,
    volume,
} from 'railway/iac';

/**
 * GameVote on Railway: one service running nginx and PHP-FPM together (the Dockerfile's
 * final `railway` stage), with SQLite and the generated APP_KEY on a persistent volume.
 * See docs/deployment.md#railway.
 *
 * Preview with `railway config plan`; apply with `railway config apply`.
 */
export default defineRailway(() => {
    const data = volume('gamevote-data', { sizeMB: 1024 });

    const app = service('gamevote', {
        source: github('webanoid/gamevote', { branch: 'main' }),
        build: { builder: 'DOCKERFILE', dockerfilePath: 'Dockerfile' },

        // Laravel's health route; nginx exempts it from HTTP basic auth.
        healthcheck: '/up',
        healthcheckTimeout: 120,

        // SQLite lives on the volume, and a volume attaches to one service: one replica only.
        replicas: 1,
        deploy: {
            restartPolicyType: 'ON_FAILURE',
            restartPolicyMaxRetries: 5,
            requiredMountPath: '/data',
        },
        volumeMounts: { '/data': data },

        env: {
            PORT: '8080',
            APP_NAME: 'GameVote',
            APP_ENV: 'production',
            APP_DEBUG: 'false',
            APP_URL: 'https://${{RAILWAY_PUBLIC_DOMAIN}}',
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: '/data/database.sqlite',
            LOG_CHANNEL: 'stderr',
            // Railway terminates HTTPS at its proxy.
            TRUSTED_PROXIES: '*',
            SESSION_SECURE_COOKIE: 'true',

            // Optional, set in the Railway dashboard; preserve() leaves existing values alone.
            // Without API_KEY_ESSENCE the app generates its own key and stores it on the volume.
            API_KEY_ESSENCE: preserve(),
            HTTP_AUTH_USER: preserve(),
            HTTP_AUTH_PASSWORD: preserve(),
        },
    });

    return project('gamevote', { resources: [data, app] });
});
