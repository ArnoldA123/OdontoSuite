<template>
  <div
    class="motif-scene pointer-events-none absolute inset-0 overflow-hidden"
    :class="{ 'has-drift': driftOn }"
    aria-hidden="true"
  >
    <div v-for="tier in tiers" :key="tier" class="motif-layer" :class="`motif-${tier}`">
      <div class="motif-drift">
        <img
          v-for="mark in marksByTier[tier]"
          :key="mark.id"
          class="motif-mark"
          :class="{ 'motif-toothbrush': mark.brush }"
          :src="mark.src"
          alt=""
          draggable="false"
          :style="markStyle(mark)"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
// WU5b / user request - the login's dental parallax scene, scaled down for
// dashboard surfaces. Same recipe as DentalParallaxBackground (three depth
// tiers with per-tier brightness/opacity and ambient drift loops) but marks
// sized in px instead of vw so a small box never swallows them, and with
// AUTOMATIC drift only (user direction: no pointer-following parallax).
// Decorative only: pointer-events-none, aria-hidden, and fully static under
// prefers-reduced-motion.
import { computed, onMounted, ref } from 'vue'

const props = defineProps({
  variant: { type: String, default: 'box' }
})

const TOOTH = '/images/login/tooth-1.png'
const BRUSH = '/images/login/toothbrush.png'

// Marks stay in the outer band so the centered copy keeps its reading lane.
const BOX_MARKS = [
  { id: 'b1', src: TOOTH, tier: 'far', x: 8, y: 12, w: 30, r: -12 },
  { id: 'b2', src: BRUSH, brush: true, tier: 'far', x: 78, y: 16, w: 34, r: 18 },
  { id: 'b3', src: TOOTH, tier: 'far', x: 90, y: 78, w: 28, r: 8 },
  { id: 'b4', src: TOOTH, tier: 'mid', x: 18, y: 82, w: 38, r: 10 },
  { id: 'b5', src: BRUSH, brush: true, tier: 'mid', x: 32, y: 10, w: 36, r: -22 },
  { id: 'b6', src: TOOTH, tier: 'mid', x: 72, y: 88, w: 36, r: -8 },
  { id: 'b7', src: TOOTH, tier: 'near', x: 92, y: 38, w: 46, r: 14 },
  { id: 'b8', src: BRUSH, brush: true, tier: 'near', x: 6, y: 46, w: 42, r: -16 }
]

// Hero: the greeting owns the left lane, so the scene composes around it.
const HERO_MARKS = [
  { id: 'h1', src: TOOTH, tier: 'far', x: 88, y: 18, w: 48, r: -10 },
  { id: 'h2', src: BRUSH, brush: true, tier: 'far', x: 72, y: 80, w: 44, r: 16 },
  { id: 'h3', src: TOOTH, tier: 'mid', x: 95, y: 52, w: 58, r: 12 },
  { id: 'h4', src: BRUSH, brush: true, tier: 'mid', x: 82, y: 28, w: 52, r: -18 },
  { id: 'h5', src: TOOTH, tier: 'near', x: 66, y: 86, w: 64, r: 6 },
  { id: 'h6', src: BRUSH, brush: true, tier: 'near', x: 93, y: 80, w: 50, r: -14 },
  { id: 'h7', src: TOOTH, tier: 'near', x: 78, y: 60, w: 56, r: 20 }
]

const tiers = ['far', 'mid', 'near']
const marks = computed(() => (props.variant === 'hero' ? HERO_MARKS : BOX_MARKS))
const marksByTier = computed(() => {
  const out = { far: [], mid: [], near: [] }
  for (const mark of marks.value) out[mark.tier].push(mark)
  return out
})

const markStyle = mark => ({
  '--mark-x': `${mark.x}%`,
  '--mark-y': `${mark.y}%`,
  '--mark-w': `${mark.w}px`,
  '--mark-r': `${mark.r}deg`
})

const driftOn = ref(true)

onMounted(() => {
  const reduced = window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches
  if (reduced) driftOn.value = false
})
</script>

<style scoped>
.motif-layer {
  position: absolute;
  inset: -32px;
}

.motif-drift {
  position: absolute;
  inset: 0;
}

.motif-mark {
  position: absolute;
  left: var(--mark-x);
  top: var(--mark-y);
  width: var(--mark-w);
  height: auto;
  transform: translate(-50%, -50%) rotate(var(--mark-r, 0deg));
  filter: brightness(var(--mark-brightness, 1));
}

/* The toothbrush ships as a near-black silhouette, so it damps its own alpha
   exactly like the login scene does. */
.motif-toothbrush {
  opacity: 0.42;
}

.motif-far {
  --mark-brightness: 0.86;
  --motif-drift-duration: 58s;
  --motif-drift-delay: 0s;
  opacity: 0.4;
}

.motif-mid {
  --mark-brightness: 0.78;
  --motif-drift-duration: 46s;
  --motif-drift-delay: 2.5s;
  opacity: 0.58;
}

.motif-near {
  --mark-brightness: 0.68;
  --motif-drift-duration: 34s;
  --motif-drift-delay: 5s;
  opacity: 0.7;
}

.has-drift .motif-drift {
  animation-timing-function: cubic-bezier(0.45, 0.05, 0.55, 0.95);
  animation-iteration-count: infinite;
  animation-fill-mode: both;
  animation-duration: var(--motif-drift-duration);
  animation-delay: var(--motif-drift-delay);
}

.has-drift .motif-far .motif-drift {
  animation-name: motif-drift-far;
}

.has-drift .motif-mid .motif-drift {
  animation-name: motif-drift-mid;
}

.has-drift .motif-near .motif-drift {
  animation-name: motif-drift-near;
}

@keyframes motif-drift-far {
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

@keyframes motif-drift-mid {
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

@keyframes motif-drift-near {
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

@media (prefers-reduced-motion: reduce) {
  .has-drift .motif-drift {
    animation: none;
  }
}
</style>
