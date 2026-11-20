<template>
  <div class="dental-parallax" :class="{ 'has-drift': driftOn }" aria-hidden="true">
    <div
      v-for="plane in PLANES"
      :key="plane.id"
      class="dental-parallax-plane"
      :class="`dental-parallax-plane--${plane.id}`"
      :data-layer="plane.id"
      :style="{ '--drift-duration': `${plane.duration}s` }"
    >
      <div class="dental-parallax-track">
        <div class="dental-parallax-tile">
          <span
            v-for="(mark, index) in plane.marks"
            :key="index"
            class="dental-parallax-mark"
            :class="`dental-parallax-mark--${mark[0]}`"
            :style="markStyle(mark)"
          />
        </div>
        <div class="dental-parallax-tile dental-parallax-tile--relay">
          <span
            v-for="(mark, index) in plane.marks"
            :key="`relay-${index}`"
            class="dental-parallax-mark"
            :class="`dental-parallax-mark--${mark[0]}`"
            :style="markStyle(mark)"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'

// One pastel field, three planes of dental marks drifting upward forever. Each
// plane repeats its 1440 x 900 tile twice and travels exactly one tile height,
// so the loop closes without a seam at any viewport height. No pointer input:
// the scene is ambient, and a reduced-motion user gets it painted static.
const TOOTH = 'tooth'
const BRUSH = 'brush'

// [glyph, left, top, size, rotation] in design px over the 1440 x 900 tile.
const FAR_MARKS = [
  [TOOTH, 157.5, 686.7, 16, 12.4],
  [BRUSH, 356.5, 220, 20.2, 22.7],
  [TOOTH, 506.8, 521.3, 14.9, 4.3],
  [BRUSH, 645.5, 388.3, 18.2, 21.3],
  [TOOTH, 1343.8, 530.2, 11.8, 7.1],
  [TOOTH, 960.1, 858.4, 11.8, 15.8],
  [TOOTH, 1176.3, 647.9, 11.4, -15],
  [BRUSH, 641.9, 533.4, 18.2, 41.7],
  [TOOTH, 358.1, 774.8, 13.1, 1.6],
  [TOOTH, 711.2, 608.7, 12.3, -1.8],
  [TOOTH, 48, 380.1, 13.8, 11.8],
  [TOOTH, 1186.4, 339.7, 12.2, 6.9],
  [TOOTH, 1233.1, 568.6, 15.8, 5.3],
  [TOOTH, 854, 356.4, 13.8, -11.8],
  [BRUSH, 1022.4, 637.5, 18.2, 24],
  [TOOTH, 939.6, 764.9, 16.9, 4.4]
]

const MID_MARKS = [
  [TOOTH, 86.2, 460.9, 22.6, -9.2],
  [TOOTH, 325.7, 537.1, 21.8, -14.5],
  [TOOTH, 417.3, 337.6, 22.8, 7.6],
  [TOOTH, 1323.2, 831.3, 21.6, 10.8],
  [BRUSH, 1187.8, 625.8, 27.9, -39.5],
  [BRUSH, 438.3, 52.2, 33.6, -24.5],
  [TOOTH, 1144.9, 490.3, 23.6, 4.5],
  [TOOTH, 143.1, 264.8, 18.7, -4.4],
  [TOOTH, 928.3, 642.6, 25.5, 4.3],
  [TOOTH, 817.7, 44.3, 18.5, -4.7],
  [BRUSH, 1353, 385, 26.5, 36.9],
  [TOOTH, 53.1, 569, 21.3, 13.1]
]

const NEAR_MARKS = [
  [TOOTH, 1170.7, 847.6, 34.2, -2.5],
  [TOOTH, 1115.4, 282.3, 36.9, -5.7],
  [TOOTH, 484.8, 826.4, 43.7, 3.8],
  [BRUSH, 492.9, 65.5, 43.1, 23.2],
  [TOOTH, 682, 313.4, 39.1, -3],
  [BRUSH, 251.7, 535.5, 48.3, -39.5],
  [TOOTH, 872.8, 353.8, 38.4, 14.6],
  [TOOTH, 483.9, 375.6, 38.2, -11.4],
  [TOOTH, 115, 324.8, 30.1, -15.5]
]

const PLANES = [
  { id: 'far', duration: 200, marks: FAR_MARKS },
  { id: 'mid', duration: 135, marks: MID_MARKS },
  { id: 'near', duration: 90, marks: NEAR_MARKS }
]

const markStyle = ([, x, y, size, rotation]) => ({
  left: `${x}px`,
  top: `${y}px`,
  width: `${size}px`,
  height: `${size}px`,
  transform: `rotate(${rotation}deg)`
})

// The loop never starts for a reduced-motion user, so the first frame is
// already at rest; the CSS block is the second guard, for a live flip.
const driftOn = ref(true)

onMounted(() => {
  if (window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches) driftOn.value = false
})
</script>

<style scoped>
/* One surface, no gradient: the pastel accent-50 is the page, and the two
   dental assets ride on it as alpha masks painted in the accent ramp. Nothing
   here reacts to the pointer; only the vertical drift moves. */
.dental-parallax {
  /* The tile height is also the loop travel: one full tile per cycle means the
     relay tile lands exactly where the first one started. */
  --drift-tile-h: 900px;
  --drift-tile-h: max(900px, 100lvh);
  position: absolute;
  inset: 0;
  overflow: hidden;
  pointer-events: none;
  background: var(--color-accent-50);
}

.dental-parallax-plane {
  position: absolute;
  inset: 0;
}

.dental-parallax-track {
  position: absolute;
  inset: 0;
}

.dental-parallax.has-drift .dental-parallax-track {
  animation-name: dental-parallax-drift;
  animation-duration: var(--drift-duration, 200s);
  animation-timing-function: linear;
  animation-iteration-count: infinite;
  will-change: transform;
}

.dental-parallax-tile {
  position: absolute;
  top: 0;
  left: 50%;
  width: 1440px;
  height: var(--drift-tile-h);
  transform: translateX(-50%);
}

.dental-parallax-tile--relay {
  top: var(--drift-tile-h);
}

@keyframes dental-parallax-drift {
  from {
    transform: translate3d(0, 0, 0);
  }
  to {
    transform: translate3d(0, calc(-1 * var(--drift-tile-h)), 0);
  }
}

/* The shape is the asset; the color is painted solid behind it. Masking keeps
   the drawing crisp at every size and lets one PNG carry the whole accent
   ramp, so no tier needs its own exported file. */
.dental-parallax-mark {
  position: absolute;
  display: block;
  background-color: var(--color-accent-200);
  -webkit-mask-image: var(--glyph);
  mask-image: var(--glyph);
  -webkit-mask-size: contain;
  mask-size: contain;
  -webkit-mask-position: center;
  mask-position: center;
  -webkit-mask-repeat: no-repeat;
  mask-repeat: no-repeat;
}

.dental-parallax-mark--tooth {
  --glyph: url('/images/login/tooth-1.png');
}

.dental-parallax-mark--brush {
  --glyph: url('/images/login/toothbrush.png');
}

/* Depth is ink, not opacity: the far plane is the palest tint of the ramp, the
   near plane the saturated one, and the thin brush stroke takes one more step
   than the tooth so it stays legible at the smallest sizes. */
.dental-parallax-plane--far .dental-parallax-mark {
  background-color: var(--color-accent-100);
}

.dental-parallax-plane--near .dental-parallax-mark {
  background-color: var(--color-accent-200);
}

.dental-parallax-plane--mid .dental-parallax-mark--brush,
.dental-parallax-plane--near .dental-parallax-mark--brush {
  background-color: var(--color-accent-300);
}

@media (prefers-reduced-motion: reduce) {
  .dental-parallax.has-drift .dental-parallax-track {
    animation: none;
    transform: none;
    will-change: auto;
  }
}
</style>
