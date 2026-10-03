<template>
  <div
    ref="rootRef"
    class="dental-parallax"
    :class="{ 'is-parallax-active': active }"
    aria-hidden="true"
  >
    <svg
      class="dental-parallax-layer dental-parallax-far"
      data-layer="far"
      viewBox="0 0 1440 900"
      preserveAspectRatio="xMidYMid slice"
      aria-hidden="true"
      focusable="false"
    >
      <defs>
        <filter id="dental-parallax-soft" x="-20%" y="-20%" width="140%" height="140%">
          <feGaussianBlur stdDeviation="10" />
        </filter>
        <path
          id="dental-parallax-tooth"
          d="M8 3.5C5.5 3.5 4 5.5 4 8c0 2.5 1.5 4 2 5.5s.5 4.5 1.5 6 1.5 1 2 0 1-3.5 1.5-3.5 1 2.5 1.5 3.5 1 1 2 0 .5-4.5 1.5-6 2-3 2-5.5c0-2.5-1.5-4.5-4-4.5-1.5 0-2 1-3 1s-1.5-1-3-1z"
        />
      </defs>
      <g fill="currentColor" filter="url(#dental-parallax-soft)">
        <use href="#dental-parallax-tooth" transform="translate(-60 60) scale(19) rotate(-10)" />
        <use href="#dental-parallax-tooth" transform="translate(690 240) scale(13) rotate(8)" />
        <use href="#dental-parallax-tooth" transform="translate(-30 560) scale(15) rotate(16)" />
        <use href="#dental-parallax-tooth" transform="translate(1090 500) scale(21) rotate(14)" />
      </g>
    </svg>

    <svg
      class="dental-parallax-layer dental-parallax-mid"
      data-layer="mid"
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
      <g transform="translate(520 460) scale(17) rotate(-14)">
        <use href="#dental-parallax-tooth" fill="currentColor" opacity="0.4" />
        <use
          href="#dental-parallax-tooth"
          fill="none"
          stroke="currentColor"
          stroke-width="1.2"
          stroke-linecap="round"
          stroke-linejoin="round"
          vector-effect="non-scaling-stroke"
          opacity="0.7"
        />
      </g>
      <g transform="translate(924 112) scale(23) rotate(10)">
        <use href="#dental-parallax-tooth" fill="currentColor" opacity="0.8" />
        <use
          href="#dental-parallax-tooth"
          fill="none"
          stroke="currentColor"
          stroke-width="1.4"
          stroke-linecap="round"
          stroke-linejoin="round"
          vector-effect="non-scaling-stroke"
          opacity="1"
        />
      </g>
    </svg>

    <svg
      class="dental-parallax-layer dental-parallax-near"
      data-layer="near"
      viewBox="0 0 1440 900"
      preserveAspectRatio="xMidYMid slice"
      aria-hidden="true"
      focusable="false"
    >
      <g fill="currentColor">
        <circle cx="96" cy="486" r="4" />
        <circle cx="168" cy="168" r="3.5" />
        <circle cx="384" cy="646" r="3.5" />
        <circle cx="662" cy="782" r="3.5" />
        <circle cx="862" cy="138" r="3.5" />
        <circle cx="1184" cy="322" r="4" />
        <circle cx="1322" cy="702" r="4" />
      </g>
      <g fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" opacity="1">
        <path d="M520 244 L562 206" />
        <path d="M1002 522 L1044 482" />
        <path d="M142 422 L178 390" />
        <path d="M762 302 L798 266" />
        <path d="M1268 176 L1302 144" />
        <path d="M1108 640 L1150 600" />
      </g>
    </svg>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { usePointerParallax } from '@/composables/usePointerParallax'

const rootRef = ref(null)

const { active } = usePointerParallax(rootRef, { response: 0.45, damping: 0.85 })
</script>

<style scoped>
/* Full-bleed vector scene. The base gradient and the three silhouette
   layers compose the depth; the layers translate against the pointer with
   amplitudes that grow toward the viewer (10px / 22px / 36px). Everything is
   vector + transforms, no raster assets. */
.dental-parallax {
  position: absolute;
  inset: 0;
  overflow: hidden;
  pointer-events: none;
  background: linear-gradient(160deg, var(--color-canvas) 0%, var(--color-accent-50) 100%);
}

.dental-parallax-layer {
  position: absolute;
  /* Overscan so the largest translation never reveals an edge. */
  inset: -48px;
  width: calc(100% + 96px);
  height: calc(100% + 96px);
  transform: translate3d(
    calc(var(--parallax-x, 0) * var(--parallax-amplitude, 0px)),
    calc(var(--parallax-y, 0) * var(--parallax-amplitude, 0px)),
    0
  );
}

/* Three full-viewport layers: promote them for the compositor only while
   the parallax is live (or settling), never at rest. */
.dental-parallax.is-parallax-active .dental-parallax-layer {
  will-change: transform;
}

.dental-parallax-far {
  --parallax-amplitude: 10px;
  color: var(--color-accent-200);
  opacity: 0.27;
}

.dental-parallax-mid {
  --parallax-amplitude: 22px;
  color: var(--color-accent-300);
  opacity: 0.5;
}

.dental-parallax-near {
  --parallax-amplitude: 36px;
  color: var(--color-accent-400);
  opacity: 0.45;
}

/* Reduced motion: the scene is fully static. The pointer listener is never
   attached either (usePointerParallax), and this rule neutralizes any
   transform left on the layers by a live preference flip. */
@media (prefers-reduced-motion: reduce) {
  .dental-parallax-layer {
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
