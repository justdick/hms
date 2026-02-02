---
inclusion: fileMatch
fileMatchPattern: "promo-video/**/*.tsx,promo-video/**/*.ts"
---

# Remotion Development

## When to Apply

Activate this skill when:

- Creating or modifying Remotion compositions
- Working with animations, sequences, or video rendering
- Using Remotion hooks like `useCurrentFrame`, `useVideoConfig`
- Building video components with `AbsoluteFill`, `Sequence`, `Series`

## Documentation

Use the `mcp_remotion_documentation_remotion_documentation` tool to search Remotion docs for specific patterns.

## Core Concepts

### Composition

A composition defines what you can render:
- React component
- Width/height (canvas size)
- FPS (frames per second)
- Duration in frames
- Unique ID

```tsx
import { Composition } from "remotion";

export const RemotionRoot: React.FC = () => {
  return (
    <Composition
      id="MyVideo"
      component={MyComponent}
      durationInFrames={150}
      fps={30}
      width={1920}
      height={1080}
    />
  );
};
```

### useCurrentFrame & useVideoConfig

```tsx
import { useCurrentFrame, useVideoConfig } from "remotion";

const MyComponent = () => {
  const frame = useCurrentFrame();
  const { fps, width, height, durationInFrames } = useVideoConfig();
  
  return <div>Frame {frame}</div>;
};
```

### Interpolation

```tsx
import { interpolate, useCurrentFrame } from "remotion";

const MyComponent = () => {
  const frame = useCurrentFrame();
  
  const opacity = interpolate(frame, [0, 30], [0, 1], {
    extrapolateLeft: "clamp",
    extrapolateRight: "clamp",
  });
  
  return <div style={{ opacity }}>Fading in</div>;
};
```

### Spring Animations

```tsx
import { spring, useCurrentFrame, useVideoConfig } from "remotion";

const MyComponent = () => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  
  const scale = spring({
    fps,
    frame,
    config: { damping: 200 },
    durationInFrames: 120,
  });
  
  return <div style={{ transform: `scale(${scale})` }}>Bouncing</div>;
};
```

### AbsoluteFill

Full-size container for layering:

```tsx
import { AbsoluteFill } from "remotion";

const MyComponent = () => {
  return (
    <AbsoluteFill style={{ backgroundColor: "blue" }}>
      <AbsoluteFill style={{ justifyContent: "center", alignItems: "center" }}>
        <h1>Centered Text</h1>
      </AbsoluteFill>
    </AbsoluteFill>
  );
};
```

### Sequence

Play components at specific times:

```tsx
import { Sequence } from "remotion";

const MyVideo = () => {
  return (
    <>
      <Sequence from={0} durationInFrames={60}>
        <Intro />
      </Sequence>
      <Sequence from={60} durationInFrames={90}>
        <MainContent />
      </Sequence>
    </>
  );
};
```

### Series

Sequential playback without manual timing:

```tsx
import { Series } from "remotion";

const MyVideo = () => {
  return (
    <Series>
      <Series.Sequence durationInFrames={60}>
        <Intro />
      </Series.Sequence>
      <Series.Sequence durationInFrames={90}>
        <MainContent />
      </Series.Sequence>
    </Series>
  );
};
```

## CLI Commands

```bash
# Start dev server
npm run dev

# Render video
npx remotion render src/index.ts CompositionId out/video.mp4

# List compositions
npx remotion compositions src/index.ts
```

## Common Patterns

### Delay Animation Start

```tsx
const frame = useCurrentFrame();
const delayedFrame = Math.max(0, frame - 30); // Start after 30 frames
```

### Loop Animation

```tsx
const frame = useCurrentFrame();
const loopDuration = 60;
const loopedFrame = frame % loopDuration;
```

### Easing

```tsx
import { interpolate, Easing } from "remotion";

const value = interpolate(frame, [0, 60], [0, 100], {
  easing: Easing.bezier(0.25, 0.1, 0.25, 1),
});
```

## Common Pitfalls

- Forgetting `extrapolateLeft/Right: "clamp"` causes values to go beyond range
- Not using `useVideoConfig()` for fps in spring animations
- Hardcoding dimensions instead of using `useVideoConfig()`
- Missing `key` props when mapping over sequences
