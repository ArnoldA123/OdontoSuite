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
              --mark-x: 36%;
              --mark-y: 14.5%;
              --mark-w: 1.9vw;
              --mark-r: -12deg;
              --mark-sm-x: 24%;
              --mark-sm-y: 8%;
              --mark-sm-w: 2vw;
              --mark-sm-r: -14deg;
            "
          />
          <img
            class="dental-parallax-mark"
            src="/images/login/tooth-1.png"
            alt=""
            draggable="false"
            style="
              --mark-x: 79.3%;
              --mark-y: 16.4%;
              --mark-w: 2vw;
              --mark-r: 14deg;
              --mark-sm-x: 62%;
              --mark-sm-y: 8.5%;
              --mark-sm-w: 2.2vw;
              --mark-sm-r: 12deg;
            "
          />
          <img
            class="dental-parallax-mark"
            src="/images/login/tooth-1.png"
            alt=""
            draggable="false"
            style="
              --mark-x: 13.7%;
              --mark-y: 40.3%;
              --mark-w: 1.8vw;
              --mark-r: -8deg;
              --mark-sm-x: 13.5%;
              --mark-sm-y: 42%;
              --mark-sm-w: 2.4vw;
              --mark-sm-r: 8deg;
            "
          />
          <img
            class="dental-parallax-mark"
            src="/images/login/tooth-1.png"
            alt=""
            draggable="false"
            style="
              --mark-x: 88.9%;
              --mark-y: 60.7%;
              --mark-w: 2.1vw;
              --mark-r: 10deg;
              --mark-sm-x: 87%;
              --mark-sm-y: 44%;
              --mark-sm-w: 1.9vw;
              --mark-sm-r: -10deg;
            "
          />
          <img
            class="dental-parallax-mark dental-parallax-toothbrush"
            src="/images/login/toothbrush.png"
            alt=""
            draggable="false"
            style="
              --mark-x: 25.1%;
              --mark-y: 85%;
              --mark-w: 2.4vw;
              --mark-r: 22deg;
              --mark-sm-x: 38%;
              --mark-sm-y: 88%;
              --mark-sm-w: 2.6vw;
              --mark-sm-r: 18deg;
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
              --mark-x: 19.4%;
              --mark-y: 17.9%;
              --mark-w: 2.5vw;
              --mark-r: 8deg;
              --mark-sm-x: 46%;
              --mark-sm-y: 9.5%;
              --mark-sm-w: 2.6vw;
              --mark-sm-r: 10deg;
            "
          />
          <img
            class="dental-parallax-mark"
            src="/images/login/tooth-1.png"
            alt=""
            draggable="false"
            style="
              --mark-x: 65.6%;
              --mark-y: 81.1%;
              --mark-w: 2.9vw;
              --mark-r: -13deg;
              --mark-sm-x: 70%;
              --mark-sm-y: 84%;
              --mark-sm-w: 2.8vw;
              --mark-sm-r: -12deg;
            "
          />
          <img
            class="dental-parallax-mark"
            src="/images/login/tooth-1.png"
            alt=""
            draggable="false"
            style="
              --mark-x: 82.5%;
              --mark-y: 49%;
              --mark-w: 2.6vw;
              --mark-r: 7deg;
              --mark-sm-x: 13.4%;
              --mark-sm-y: 60%;
              --mark-sm-w: 3vw;
              --mark-sm-r: -9deg;
            "
          />
          <img
            class="dental-parallax-mark dental-parallax-toothbrush"
            src="/images/login/toothbrush.png"
            alt=""
            draggable="false"
            style="
              --mark-x: 48.7%;
              --mark-y: 85.5%;
              --mark-w: 3.2vw;
              --mark-r: -26deg;
              --mark-sm-x: 56%;
              --mark-sm-y: 11.5%;
              --mark-sm-w: 3.4vw;
              --mark-sm-r: -22deg;
            "
          />
          <img
            class="dental-parallax-mark dental-parallax-toothbrush"
            src="/images/login/toothbrush.png"
            alt=""
            draggable="false"
            style="
              --mark-x: 90.5%;
              --mark-y: 30.5%;
              --mark-w: 3.3vw;
              --mark-r: 30deg;
              --mark-sm-x: 33%;
              --mark-sm-y: 90%;
              --mark-sm-w: 3.6vw;
              --mark-sm-r: 26deg;
            "
          />
        </div>
      </div>
    </div>

    <div class="dental-parallax-layer dental-parallax-near" data-layer="near">
      <div class="dental-parallax-drift">
        <div class="dental-parallax-art">
          <img
            class="dental-parallax-mark"
            src="/images/login/tooth-1.png"
            alt=""
            draggable="false"
            style="
              --mark-x: 14.3%;
              --mark-y: 65.6%;
              --mark-w: 3.6vw;
              --mark-r: -16deg;
              --mark-sm-x: 79%;
              --mark-sm-y: 8%;
              --mark-sm-w: 3.4vw;
              --mark-sm-r: -15deg;
            "
          />
          <img
            class="dental-parallax-mark"
            src="/images/login/tooth-1.png"
            alt=""
            draggable="false"
            style="
              --mark-x: 61.5%;
              --mark-y: 17.4%;
              --mark-w: 4vw;
              --mark-r: 9deg;
              --mark-sm-x: 20%;
              --mark-sm-y: 87%;
              --mark-sm-w: 3.8vw;
              --mark-sm-r: 11deg;
            "
          />
          <img
            class="dental-parallax-mark"
            src="/images/login/tooth-1.png"
            alt=""
            draggable="false"
            style="
              --mark-x: 73%;
              --mark-y: 84.5%;
              --mark-w: 4.4vw;
              --mark-r: -11deg;
              --mark-sm-x: 64%;
              --mark-sm-y: 91%;
              --mark-sm-w: 4.2vw;
              --mark-sm-r: -10deg;
            "
          />
          <img
            class="dental-parallax-mark dental-parallax-toothbrush"
            src="/images/login/toothbrush.png"
            alt=""
            draggable="false"
            style="
              --mark-x: 87%;
              --mark-y: 58.8%;
              --mark-w: 4.8vw;
              --mark-r: 24deg;
              --mark-sm-x: 74%;
              --mark-sm-y: 92%;
              --mark-sm-w: 4.8vw;
              --mark-sm-r: 20deg;
            "
          />
          <img
            class="dental-parallax-mark dental-parallax-toothbrush"
            src="/images/login/toothbrush.png"
            alt=""
            draggable="false"
            style="
              --mark-x: 25.5%;
              --mark-y: 15.5%;
              --mark-w: 5.2vw;
              --mark-r: -20deg;
              --mark-sm-x: 30%;
              --mark-sm-y: 12%;
              --mark-sm-w: 5.2vw;
              --mark-sm-r: -18deg;
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
   ambient drift loop (on the inner wrapper). Only the plain tooth and the
   toothbrush are placed, in small and varied sizes, so the centered form card
   stays the subject while the scene distributes around it. The light-gray
   tooth PNGs are darkened per tier through --mark-brightness so every mark
   stays clearly perceptible over the light canvas. */
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
  opacity: 0.4;
}

.dental-parallax-mid {
  --parallax-amplitude: 22px;
  --dental-drift-duration: 46s;
  --dental-drift-delay: 2.5s;
  --mark-brightness: 0.78;
  opacity: 0.58;
}

.dental-parallax-near {
  --parallax-amplitude: 36px;
  --dental-drift-duration: 34s;
  --dental-drift-delay: 5s;
  --mark-brightness: 0.68;
  opacity: 0.7;
}

/* Small screens: the centered card owns the middle of the viewport, so the
   design-frame spread leaves it only edge slivers. Every mark switches to
   its --mark-sm-* slot (the top and bottom bands plus two clipped side
   edges) by rebinding the layout properties themselves; the inline base
   custom properties stay as the fallback, which keeps the override outside
   the inline-vs-stylesheet cascade. */
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
