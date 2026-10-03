<template>
  <div
    ref="rootRef"
    class="dental-parallax"
    :class="{ 'is-parallax-active': active, 'has-drift': driftActive }"
    aria-hidden="true"
  >
    <div class="dental-parallax-layer dental-parallax-far" data-layer="far">
      <div class="dental-parallax-drift">
        <svg
          class="dental-parallax-art"
          viewBox="0 0 1440 900"
          preserveAspectRatio="xMidYMid slice"
          aria-hidden="true"
          focusable="false"
        >
          <defs>
            <filter id="dental-parallax-soft" x="-20%" y="-20%" width="140%" height="140%">
              <feGaussianBlur stdDeviation="6" />
            </filter>
            <!-- Crown + root silhouettes, authored inside a padded frame
                 that every mark re-declares through x/y/width/height. -->
            <symbol id="dental-parallax-tooth-molar" viewBox="-8.6 -11.4 17.2 22.8">
              <path
                vector-effect="non-scaling-stroke"
                d="M-7.33 -7.6 C-7.93 -5.92 -8.33 -4.33 -7.63 -2.55 C-6.94 -0.76 -6.24 0.63 -5.35 2.81 C-4.36 5.29 -4.56 8.06 -3.37 10.04 C-2.58 11.33 -1.09 10.84 -0.89 9.15 C-0.69 6.97 -1.19 4.79 -0.59 3.2 C-0.3 2.41 0.3 2.41 0.59 3.2 C1.19 4.79 0.69 6.97 0.89 9.15 C1.09 10.84 2.58 11.33 3.37 10.04 C4.56 8.06 4.36 5.29 5.35 2.81 C6.24 0.63 6.94 -0.76 7.63 -2.55 C8.33 -4.33 7.93 -5.92 7.33 -7.6 C6.74 -9.38 5.25 -10.77 3.96 -10.77 C2.78 -10.77 1.59 -9.58 0 -9.58 C-1.59 -9.58 -2.78 -10.77 -3.96 -10.77 C-5.25 -10.77 -6.74 -9.38 -7.33 -7.6 Z"
              />
            </symbol>
            <symbol id="dental-parallax-tooth-premolar" viewBox="-6.35 -11.1 12.7 22.2">
              <path
                vector-effect="non-scaling-stroke"
                d="M-4.39 -8.79 C-5.51 -7.36 -6.02 -5.52 -5.61 -3.58 C-5.1 -1.14 -4.49 0.39 -3.88 2.64 C-3.16 5.29 -3.16 7.94 -1.63 9.68 C-0.71 10.7 0.71 10.7 1.63 9.68 C3.16 7.94 3.16 5.29 3.88 2.64 C4.49 0.39 5.1 -1.14 5.61 -3.58 C6.02 -5.52 5.51 -7.36 4.39 -8.79 C3.47 -10.11 2.14 -10.72 1.02 -10.32 C0.31 -10.01 -0.2 -9.4 -0.71 -9.4 C-1.22 -9.4 -1.73 -10.01 -2.45 -10.32 C-3.57 -10.72 -4.9 -10.11 -4.39 -8.79 Z"
              />
            </symbol>
            <symbol id="dental-parallax-tooth-incisor" viewBox="-5.1 -11.9 10.2 23.8">
              <path
                vector-effect="non-scaling-stroke"
                d="M-4.5 -9.73 C-4.5 -10.85 -2.97 -11.57 0 -11.57 C2.97 -11.57 4.5 -10.85 4.5 -9.73 C4.5 -6.66 3.58 -4 3.17 -1.14 C2.66 2.54 3.17 6.84 1.43 10.73 C0.92 11.85 -0.92 11.85 -1.43 10.73 C-3.17 6.84 -2.66 2.54 -3.17 -1.14 C-3.58 -4 -4.5 -6.66 -4.5 -9.73 Z"
              />
            </symbol>
          </defs>
          <g filter="url(#dental-parallax-soft)">
            <use
              href="#dental-parallax-tooth-incisor"
              x="-5.1"
              y="-11.9"
              width="10.2"
              height="23.8"
              fill="currentColor"
              transform="translate(-40 120) rotate(-14) scale(4.4)"
            />
            <use
              href="#dental-parallax-tooth-premolar"
              x="-6.35"
              y="-11.1"
              width="12.7"
              height="22.2"
              fill="currentColor"
              transform="translate(330 170) rotate(9) scale(3.2)"
            />
            <use
              href="#dental-parallax-tooth-molar"
              x="-8.6"
              y="-11.4"
              width="17.2"
              height="22.8"
              fill="currentColor"
              transform="translate(700 90) rotate(-7) scale(2.6)"
            />
            <use
              href="#dental-parallax-tooth-premolar"
              x="-6.35"
              y="-11.1"
              width="12.7"
              height="22.2"
              fill="currentColor"
              transform="translate(190 660) rotate(13) scale(2.9)"
            />
            <use
              href="#dental-parallax-tooth-incisor"
              x="-5.1"
              y="-11.9"
              width="10.2"
              height="23.8"
              fill="currentColor"
              transform="translate(840 600) rotate(-18) scale(4)"
            />
            <use
              href="#dental-parallax-tooth-molar"
              x="-8.6"
              y="-11.4"
              width="17.2"
              height="22.8"
              fill="currentColor"
              transform="translate(1240 260) rotate(11) scale(2.7)"
            />
            <use
              href="#dental-parallax-tooth-premolar"
              x="-6.35"
              y="-11.1"
              width="12.7"
              height="22.2"
              fill="currentColor"
              transform="translate(1090 760) rotate(-9) scale(3.4)"
            />
          </g>
        </svg>
      </div>
    </div>

    <div class="dental-parallax-layer dental-parallax-mid" data-layer="mid">
      <div class="dental-parallax-drift">
        <svg
          class="dental-parallax-art"
          viewBox="0 0 1440 900"
          preserveAspectRatio="xMidYMid slice"
          aria-hidden="true"
          focusable="false"
        >
          <path
            d="M0 606 C 240 552 430 692 724 630 C 1016 568 1196 704 1440 640 L1440 900 L0 900 Z"
            fill="currentColor"
            opacity="0.55"
          />
          <path
            d="M0 748 C 300 686 546 812 844 750 C 1142 688 1284 824 1440 766 L1440 900 L0 900 Z"
            fill="currentColor"
            opacity="0.42"
          />
          <use
            href="#dental-parallax-tooth-molar"
            x="-8.6"
            y="-11.4"
            width="17.2"
            height="22.8"
            fill="currentColor"
            stroke="currentColor"
            stroke-width="1.2"
            stroke-linejoin="round"
            opacity="1"
            transform="translate(780 210) rotate(-10) scale(4.2)"
          />
          <use
            href="#dental-parallax-tooth-incisor"
            x="-5.1"
            y="-11.9"
            width="10.2"
            height="23.8"
            fill="currentColor"
            stroke="currentColor"
            stroke-width="1.2"
            stroke-linejoin="round"
            opacity="0.72"
            transform="translate(1010 470) rotate(15) scale(7.2)"
          />
          <use
            href="#dental-parallax-tooth-premolar"
            x="-6.35"
            y="-11.1"
            width="12.7"
            height="22.2"
            fill="currentColor"
            stroke="currentColor"
            stroke-width="1.2"
            stroke-linejoin="round"
            opacity="0.72"
            transform="translate(1190 640) rotate(-7) scale(5.8)"
          />
          <use
            href="#dental-parallax-tooth-premolar"
            x="-6.35"
            y="-11.1"
            width="12.7"
            height="22.2"
            fill="currentColor"
            stroke="currentColor"
            stroke-width="1.2"
            stroke-linejoin="round"
            opacity="0.6"
            transform="translate(150 810) rotate(12) scale(4.9)"
          />
        </svg>
      </div>
    </div>

    <div class="dental-parallax-layer dental-parallax-near" data-layer="near">
      <div class="dental-parallax-drift">
        <svg
          class="dental-parallax-art"
          viewBox="0 0 1440 900"
          preserveAspectRatio="xMidYMid slice"
          aria-hidden="true"
          focusable="false"
        >
          <use
            href="#dental-parallax-tooth-molar"
            x="-8.6"
            y="-11.4"
            width="17.2"
            height="22.8"
            fill="none"
            stroke="currentColor"
            stroke-width="1.75"
            stroke-linejoin="round"
            transform="translate(1190 400) rotate(8) scale(7.2)"
          />
          <use
            href="#dental-parallax-tooth-premolar"
            x="-6.35"
            y="-11.1"
            width="12.7"
            height="22.2"
            fill="none"
            stroke="currentColor"
            stroke-width="1.75"
            stroke-linejoin="round"
            transform="translate(1210 150) rotate(-6) scale(10)"
          />
          <use
            href="#dental-parallax-tooth-incisor"
            x="-5.1"
            y="-11.9"
            width="10.2"
            height="23.8"
            fill="none"
            stroke="currentColor"
            stroke-width="1.75"
            stroke-linejoin="round"
            transform="translate(920 720) rotate(-11) scale(12.5)"
          />
          <g fill="currentColor">
            <circle cx="96" cy="486" r="4" />
            <circle cx="168" cy="168" r="3.5" />
            <circle cx="384" cy="646" r="3.5" />
            <circle cx="662" cy="782" r="3.5" />
            <circle cx="862" cy="138" r="3.5" />
            <circle cx="1296" cy="246" r="4" />
            <circle cx="1322" cy="702" r="4" />
          </g>
          <g
            fill="none"
            stroke="currentColor"
            stroke-width="1.75"
            stroke-linecap="round"
            opacity="1"
          >
            <path d="M520 244 L562 206" />
            <path d="M1060 560 L1102 520" />
            <path d="M142 422 L178 390" />
            <path d="M762 302 L798 266" />
            <path d="M1322 316 L1356 284" />
            <path d="M1108 640 L1150 600" />
          </g>
        </svg>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue'
import { usePointerParallax } from '@/composables/usePointerParallax'

const rootRef = ref(null)

const { active, reduced } = usePointerParallax(rootRef, { response: 0.45, damping: 0.85 })

// Ambient drift starts after mount, so a reduced-motion user never sees a
// first frame of autonomous motion. The CSS block is the second guard, for a
// live preference flip.
const driftActive = ref(false)

onMounted(() => {
  driftActive.value = !reduced.value
})

watch(reduced, value => {
  driftActive.value = !value
})
</script>

<style scoped>
/* Full-bleed vector scene. The base gradient plus three depth tiers compose
   the scene, and each tier stacks two independent transforms: the pointer
   offset (CSS vars on the layer, written by usePointerParallax) and the
   ambient drift loop (on the inner wrapper). Everything is vector +
   transforms, no raster assets. */
.dental-parallax {
  position: absolute;
  inset: 0;
  overflow: hidden;
  pointer-events: none;
  background: linear-gradient(160deg, var(--color-canvas) 0%, var(--color-accent-50) 100%);
}

.dental-parallax-layer {
  position: absolute;
  /* Overscan so the largest pointer amplitude plus the widest drift travel
     never reveals an edge. */
  inset: -64px;
  width: calc(100% + 128px);
  height: calc(100% + 128px);
  transform: translate3d(
    calc(var(--parallax-x, 0) * var(--parallax-amplitude, 0px)),
    calc(var(--parallax-y, 0) * var(--parallax-amplitude, 0px)),
    0
  );
}

.dental-parallax-drift {
  position: absolute;
  inset: 0;
}

.dental-parallax-art {
  display: block;
  width: 100%;
  height: 100%;
}

/* Three full-viewport layers: promote them for the compositor only while
   the parallax is live (or settling), never at rest. */
.dental-parallax.is-parallax-active .dental-parallax-layer {
  will-change: transform;
}

/* Ambient drift: a slow loop per tier, off under reduced motion. Transform
   only, so the compositor owns the motion and nothing repaints; the loops
   differ in duration and start phase, and each returns to its origin. Every
   animation-name is written literally (never through a variable) so the
   scoped-style keyframe rename rewrites name and reference together. */
.dental-parallax.has-drift .dental-parallax-drift {
  animation-duration: var(--dental-drift-duration);
  animation-delay: var(--dental-drift-delay);
  animation-timing-function: cubic-bezier(0.45, 0.05, 0.55, 0.95);
  animation-iteration-count: infinite;
  animation-fill-mode: both;
}

.dental-parallax.has-drift .dental-parallax-far .dental-parallax-drift {
  animation-name: dental-parallax-drift-far;
}

.dental-parallax.has-drift .dental-parallax-mid .dental-parallax-drift {
  animation-name: dental-parallax-drift-mid;
}

.dental-parallax.has-drift .dental-parallax-near .dental-parallax-drift {
  animation-name: dental-parallax-drift-near;
}

@keyframes dental-parallax-drift-far {
  0% {
    transform: translate3d(0, 0, 0);
  }
  35% {
    transform: translate3d(-9px, 7px, 0);
  }
  70% {
    transform: translate3d(8px, -10px, 0);
  }
  100% {
    transform: translate3d(0, 0, 0);
  }
}

@keyframes dental-parallax-drift-mid {
  0% {
    transform: translate3d(0, 0, 0);
  }
  30% {
    transform: translate3d(13px, -11px, 0);
  }
  65% {
    transform: translate3d(-14px, 12px, 0);
  }
  100% {
    transform: translate3d(0, 0, 0);
  }
}

@keyframes dental-parallax-drift-near {
  0% {
    transform: translate3d(0, 0, 0);
  }
  32% {
    transform: translate3d(-19px, 14px, 0);
  }
  68% {
    transform: translate3d(17px, -20px, 0);
  }
  100% {
    transform: translate3d(0, 0, 0);
  }
}

.dental-parallax-far {
  --parallax-amplitude: 10px;
  --dental-drift-duration: 58s;
  --dental-drift-delay: 0s;
  color: var(--color-accent-200);
  opacity: 0.27;
}

.dental-parallax-mid {
  --parallax-amplitude: 22px;
  --dental-drift-duration: 46s;
  --dental-drift-delay: 2.5s;
  color: var(--color-accent-300);
  opacity: 0.5;
}

.dental-parallax-near {
  --parallax-amplitude: 36px;
  --dental-drift-duration: 34s;
  --dental-drift-delay: 5s;
  color: var(--color-accent-400);
  opacity: 0.45;
}

/* Reduced motion: the scene is fully static. The pointer listener is never
   attached either (usePointerParallax) and the drift class is never applied,
   and this rule neutralizes any transform or loop left on by a live
   preference flip. */
@media (prefers-reduced-motion: reduce) {
  .dental-parallax-layer {
    transform: none !important;
  }

  .dental-parallax-drift {
    animation: none !important;
    transform: none !important;
  }
}

/* Reduced transparency: keep the scene and drop the translucency. The
   layers stay fully visible; each tier repaints from a solid token tint so
   the far/near hierarchy survives without alpha compositing. */
@media (prefers-reduced-transparency: reduce) {
  .dental-parallax-layer {
    opacity: 1;
  }

  .dental-parallax-far {
    color: var(--color-accent-100);
  }

  .dental-parallax-mid {
    color: var(--color-accent-200);
  }

  .dental-parallax-near {
    color: var(--color-accent-300);
  }

  /* The marks carry opacity presentation attributes; neutralize them so
     every mark paints its tier tint as a solid fill. */
  .dental-parallax-layer [opacity] {
    opacity: 1;
  }
}
</style>
