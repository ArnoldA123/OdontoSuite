// Slice 08 / FF-005: removed the unused axios import. The frontend uses
// the native fetch API exclusively (see useApi.js). Removing this import
// drops the axios bundle chunk that was bundled but never consumed.
//
// This file remains as the canonical place to bootstrap any global
// runtime configuration (env vars, window.__APP_CONFIG__, polyfills, etc.).
//
// Intentionally left empty: any window.__APP_CONFIG__ hint that blade
// wants to expose for the SPA bootstrap goes here.
