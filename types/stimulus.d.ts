// ABOUTME: Ambient type augmentation so `tsc --checkJs` tolerates Stimulus's dynamically
// ABOUTME: injected controller members (xxxTarget / xxxValue / xxxClass) without per-file noise.

// Stimulus generates `nameTarget`, `nameTargets`, `hasNameTarget`, `nameValue`,
// `nameClass`, etc. as prototype getters at runtime from each controller's static
// `targets` / `values` / `classes`. TypeScript can't see them, so checkJs reports
// every access as "Property 'fooTarget' does not exist". Declaring them as real class
// fields would shadow those getters and break the runtime, so we widen the base
// Controller with an index signature instead. This is the proportional baseline: it
// silences the magic-member noise while leaving all other type checking (DOM APIs,
// arg counts, real typos on non-magic locals) active. Ratchet later by giving
// individual controllers precise per-member types and narrowing this away.
import '@hotwired/stimulus';

declare module '@hotwired/stimulus' {
    interface Controller {
        // biome-ignore lint: index signature is intentionally broad for the magic members
        [member: string]: any;
    }
}
