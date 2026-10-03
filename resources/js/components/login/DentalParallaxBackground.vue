<template>
  <div
    ref="rootRef"
    class="dental-parallax"
    :class="{ 'is-parallax-active': active, 'has-drift': driftActive }"
    aria-hidden="true"
  >
    <div class="dental-parallax-layer dental-parallax-far" data-layer="far">
      <div class="dental-parallax-drift">
        <div class="dental-parallax-art">
          <img
            class="dental-parallax-mark"
            src="/images/login/tooth-1.png"
            alt=""
            draggable="false"
            style="
              --mark-x: -1%;
              --mark-y: 14%;
              --mark-w: 3.4vw;
              --mark-r: -14deg;
              --mark-sm-x: 19.7%;
              --mark-sm-y: 11.3%;
              --mark-sm-w: 17vw;
              --mark-sm-r: -12deg;
            "
          />
          <img
            class="dental-parallax-mark dental-parallax-sparkle"
            src="/images/login/tooth-2.png"
            alt=""
            draggable="false"
            style="
              --mark-x: 23%;
              --mark-y: 19%;
              --mark-w: 3.6vw;
              --mark-r: 12deg;
              --mark-sm-x: 35.1%;
              --mark-sm-y: 10.5%;
              --mark-sm-w: 14vw;
              --mark-sm-r: -6deg;
            "
          />
          <img
            class="dental-parallax-mark"
            src="/images/login/tooth-1.png"
            alt=""
            draggable="false"
            style="
              --mark-x: 49%;
              --mark-y: 10%;
              --mark-w: 3.2vw;
              --mark-r: -7deg;
              --mark-sm-x: 48.6%;
              --mark-sm-y: 11.9%;
              --mark-sm-w: 16vw;
              --mark-sm-r: 10deg;
            "
          />
          <img
            class="dental-parallax-mark dental-parallax-sparkle"
            src="/images/login/tooth-2.png"
            alt=""
            draggable="false"
            style="
              --mark-x: 13%;
              --mark-y: 73%;
              --mark-w: 3.6vw;
              --mark-r: 16deg;
              --mark-sm-x: 41.7%;
              --mark-sm-y: 86.4%;
              --mark-sm-w: 14vw;
              --mark-sm-r: -12deg;
            "
          />
          <img
            class="dental-parallax-mark"
            src="/images/login/tooth-1.png"
            alt=""
            draggable="false"
            style="
              --mark-x: 58%;
              --mark-y: 67%;
              --mark-w: 3.4vw;
              --mark-r: -18deg;
              --mark-sm-x: 83.4%;
              --mark-sm-y: 87.9%;
              --mark-sm-w: 13vw;
              --mark-sm-r: -8deg;
            "
          />
          <img
            class="dental-parallax-mark"
            src="/images/login/tooth-1.png"
            alt=""
            draggable="false"
            style="
              --mark-x: 86%;
              --mark-y: 29%;
              --mark-w: 3.2vw;
              --mark-r: 11deg;
              --mark-sm-x: -14.7%;
              --mark-sm-y: 37.4%;
              --mark-sm-w: 16vw;
              --mark-sm-r: -10deg;
            "
          />
          <img
            class="dental-parallax-mark"
            src="/images/login/tooth-1.png"
            alt=""
            draggable="false"
            style="
              --mark-x: 76%;
              --mark-y: 84%;
              --mark-w: 3.6vw;
              --mark-r: -9deg;
              --mark-sm-x: 116.6%;
              --mark-sm-y: 60.1%;
              --mark-sm-w: 18vw;
              --mark-sm-r: 12deg;
            "
          />
        </div>
      </div>
    </div>

    <div class="dental-parallax-layer dental-parallax-mid" data-layer="mid">
      <div class="dental-parallax-drift">
        <div class="dental-parallax-art">
          <img
            class="dental-parallax-mark"
            src="/images/login/tooth-1.png"
            alt=""
            draggable="false"
            style="
              --mark-x: 54%;
              --mark-y: 23%;
              --mark-w: 5.4vw;
              --mark-r: -10deg;
              --mark-sm-x: 80.3%;
              --mark-sm-y: 10.9%;
              --mark-sm-w: 18vw;
              --mark-sm-r: 12deg;
            "
          />
          <img
            class="dental-parallax-mark dental-parallax-sparkle"
            src="/images/login/tooth-2.png"
            alt=""
            draggable="false"
            style="
              --mark-x: 70%;
              --mark-y: 52%;
              --mark-w: 5.8vw;
              --mark-r: 15deg;
              --mark-sm-x: 50%;
              --mark-sm-y: 104.3%;
              --mark-sm-w: 20vw;
              --mark-sm-r: 10deg;
            "
          />
          <img
            class="dental-parallax-mark"
            src="/images/login/tooth-1.png"
            alt=""
            draggable="false"
            style="
              --mark-x: 83%;
              --mark-y: 71%;
              --mark-w: 5.6vw;
              --mark-r: -7deg;
              --mark-sm-x: 71.4%;
              --mark-sm-y: 86.6%;
              --mark-sm-w: 15vw;
              --mark-sm-r: 6deg;
            "
          />
          <img
            class="dental-parallax-mark dental-parallax-sparkle"
            src="/images/login/tooth-2.png"
            alt=""
            draggable="false"
            style="
              --mark-x: 10.5%;
              --mark-y: 90%;
              --mark-w: 5vw;
              --mark-r: 12deg;
              --mark-sm-x: 50%;
              --mark-sm-y: -6.8%;
              --mark-sm-w: 20vw;
              --mark-sm-r: -10deg;
            "
          />
        </div>
      </div>
    </div>

    <div class="dental-parallax-layer dental-parallax-near" data-layer="near">
      <div class="dental-parallax-drift">
        <div class="dental-parallax-art">
          <img
            class="dental-parallax-mark dental-parallax-sparkle"
            src="/images/login/tooth-2.png"
            alt=""
            draggable="false"
            style="
              --mark-x: 83%;
              --mark-y: 44%;
              --mark-w: 8.6vw;
              --mark-r: 8deg;
              --mark-sm-x: -20.5%;
              --mark-sm-y: 37.4%;
              --mark-sm-w: 22vw;
              --mark-sm-r: 8deg;
            "
          />
          <img
            class="dental-parallax-mark"
            src="/images/login/tooth-1.png"
            alt=""
            draggable="false"
            style="
              --mark-x: 84%;
              --mark-y: 16%;
              --mark-w: 8.8vw;
              --mark-r: -6deg;
              --mark-sm-x: 64.1%;
              --mark-sm-y: 11.1%;
              --mark-sm-w: 19vw;
              --mark-sm-r: -8deg;
            "
          />
          <img
            class="dental-parallax-mark"
            src="/images/login/tooth-1.png"
            alt=""
            draggable="false"
            style="
              --mark-x: 64%;
              --mark-y: 80%;
              --mark-w: 8.4vw;
              --mark-r: -11deg;
              --mark-sm-x: 56.8%;
              --mark-sm-y: 87.9%;
              --mark-sm-w: 19vw;
              --mark-sm-r: 9deg;
            "
          />
          <img
            class="dental-parallax-mark dental-parallax-toothbrush"
            src="/images/login/toothbrush.png"
            alt=""
            draggable="false"
            style="
              --mark-x: 7%;
              --mark-y: 72%;
              --mark-w: 11vw;
              --mark-r: 26deg;
              --mark-sm-x: 23.9%;
              --mark-sm-y: 88.3%;
              --mark-sm-w: 26vw;
              --mark-sm-r: 8deg;
            "
          />
        </div>
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
/* Full-bleed raster scene. The base gradient plus three depth tiers compose
   the scene, and each tier stacks two independent transforms: the pointer
   offset (CSS vars on the layer, written by usePointerParallax) and the
   ambient drift loop (on the inner wrapper). The light-gray tooth PNGs are
   darkened per tier through --mark-brightness so every mark stays clearly
   perceptible over the light canvas. */
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
  position: absolute;
  inset: 0;
  display: block;
  width: 100%;
  height: 100%;
}

/* Each mark centers itself on its design-frame coordinates and consumes the
   per-tier brightness, so the far tier stays faint and the near tier reads
   strongest without repainting the assets. */
.dental-parallax-mark {
  position: absolute;
  left: var(--mark-x);
  top: var(--mark-y);
  width: var(--mark-w);
  height: auto;
  transform: translate(-50%, -50%) rotate(var(--mark-r, 0deg));
  filter: brightness(var(--mark-brightness, 1));
}

/* The toothbrush ships as a near-black silhouette, so it damps its own alpha
   instead of rendering at the tooth tint's strength. */
.dental-parallax-toothbrush {
  opacity: 0.42;
}

/* The sparkle tooth keeps its warm stars: brightness stays on the tier ramp,
   saturation holds the orange, and a soft warm shadow seats it on the canvas. */
.dental-parallax-sparkle {
  filter: brightness(var(--mark-brightness, 1)) saturate(1.08)
    drop-shadow(0 2px 6px rgba(255, 171, 73, 0.28));
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
  --mark-brightness: 0.86;
  opacity: 0.34;
}

.dental-parallax-mid {
  --parallax-amplitude: 22px;
  --dental-drift-duration: 46s;
  --dental-drift-delay: 2.5s;
  --mark-brightness: 0.78;
  opacity: 0.52;
}

.dental-parallax-near {
  --parallax-amplitude: 36px;
  --dental-drift-duration: 34s;
  --dental-drift-delay: 5s;
  --mark-brightness: 0.68;
  opacity: 0.62;
}

/* Small screens: the centred card owns the middle of the viewport, so the
   design-frame spread leaves it only edge slivers. Every mark switches to
   its --mark-sm-* slot (the top and bottom bands plus the clipped side
   corners) by rebinding the layout properties themselves; the inline base
   custom properties stay as the fallback, which keeps the override outside
   the inline-vs-stylesheet cascade. Five rear marks park off-frame and the
   ten-mark constellation keeps every tier represented around the card. */
@media (max-width: 640px) {
  .dental-parallax-mark {
    left: var(--mark-sm-x, var(--mark-x));
    top: var(--mark-sm-y, var(--mark-y));
    width: var(--mark-sm-w, var(--mark-w));
    transform: translate(-50%, -50%) rotate(var(--mark-sm-r, var(--mark-r, 0deg)));
  }
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

/* Reduced transparency: keep the scene and drop the translucency. Every
   layer paints at full opacity while the per-tier brightness treatment stays
   on the marks, so the hierarchy survives without alpha compositing. */
@media (prefers-reduced-transparency: reduce) {
  .dental-parallax-layer {
    opacity: 1;
  }
}
</style>
