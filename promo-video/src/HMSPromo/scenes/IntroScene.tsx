import { AbsoluteFill, interpolate, useCurrentFrame, spring, useVideoConfig } from "remotion";

interface IntroSceneProps {
  primaryColor: string;
}

export const IntroScene: React.FC<IntroSceneProps> = ({ primaryColor }) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();

  const textOpacity = interpolate(frame, [0, 20], [0, 1], {
    extrapolateRight: "clamp",
  });

  const textScale = spring({
    frame,
    fps,
    config: { damping: 100, stiffness: 200 },
  });

  const questionMarkBounce = spring({
    frame: frame - 30,
    fps,
    config: { damping: 10, stiffness: 100 },
  });

  const fadeOut = interpolate(frame, [70, 90], [1, 0], {
    extrapolateLeft: "clamp",
    extrapolateRight: "clamp",
  });

  // Animated gradient orbs
  const orb1X = Math.sin(frame * 0.02) * 50;
  const orb1Y = Math.cos(frame * 0.015) * 30;
  const orb2X = Math.cos(frame * 0.025) * 40;
  const orb2Y = Math.sin(frame * 0.02) * 40;

  // Particle positions
  const particles = Array.from({ length: 20 }, (_, i) => ({
    x: (i * 97) % 100,
    y: (i * 73) % 100,
    size: 2 + (i % 3),
    speed: 0.5 + (i % 5) * 0.2,
    delay: i * 3,
  }));

  return (
    <AbsoluteFill
      style={{
        background: "linear-gradient(135deg, #0a0f1a 0%, #111827 50%, #0f172a 100%)",
        justifyContent: "center",
        alignItems: "center",
        opacity: fadeOut,
        overflow: "hidden",
      }}
    >
      {/* Animated gradient orbs */}
      <div
        style={{
          position: "absolute",
          top: `calc(20% + ${orb1Y}px)`,
          left: `calc(15% + ${orb1X}px)`,
          width: 500,
          height: 500,
          background: `radial-gradient(circle, ${primaryColor}20 0%, transparent 70%)`,
          borderRadius: "50%",
          filter: "blur(80px)",
        }}
      />
      <div
        style={{
          position: "absolute",
          bottom: `calc(10% + ${orb2Y}px)`,
          right: `calc(10% + ${orb2X}px)`,
          width: 400,
          height: 400,
          background: `radial-gradient(circle, #3b82f620 0%, transparent 70%)`,
          borderRadius: "50%",
          filter: "blur(60px)",
        }}
      />

      {/* Floating particles */}
      {particles.map((particle, i) => {
        const particleY = interpolate(
          (frame + particle.delay) * particle.speed,
          [0, 100],
          [100, -10],
          { extrapolateRight: "extend" }
        ) % 110;
        const particleOpacity = interpolate(frame, [0, 15], [0, 0.4], {
          extrapolateRight: "clamp",
        });

        return (
          <div
            key={i}
            style={{
              position: "absolute",
              left: `${particle.x}%`,
              top: `${particleY}%`,
              width: particle.size,
              height: particle.size,
              background: i % 2 === 0 ? primaryColor : "#3b82f6",
              borderRadius: "50%",
              opacity: particleOpacity,
              boxShadow: `0 0 ${particle.size * 2}px ${i % 2 === 0 ? primaryColor : "#3b82f6"}`,
            }}
          />
        );
      })}

      {/* Grid pattern overlay */}
      <div
        style={{
          position: "absolute",
          inset: 0,
          backgroundImage: `
            linear-gradient(rgba(255,255,255,0.02) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255,255,255,0.02) 1px, transparent 1px)
          `,
          backgroundSize: "60px 60px",
          opacity: 0.5,
        }}
      />

      <div
        style={{
          display: "flex",
          flexDirection: "column",
          alignItems: "center",
          gap: 30,
          opacity: textOpacity,
          transform: `scale(${textScale})`,
          zIndex: 10,
          padding: "0 60px",
        }}
      >
        {/* Decorative line */}
        <div
          style={{
            width: interpolate(frame, [5, 25], [0, 200], { extrapolateRight: "clamp" }),
            height: 4,
            background: `linear-gradient(90deg, transparent, ${primaryColor}, transparent)`,
            borderRadius: 2,
            marginBottom: 30,
          }}
        />

        <div
          style={{
            fontSize: 72,
            fontWeight: 800,
            color: "white",
            textAlign: "center",
            lineHeight: 1.2,
            textShadow: "0 4px 30px rgba(0,0,0,0.5)",
            letterSpacing: -1,
          }}
        >
          Managing a hospital
        </div>
        <div
          style={{
            fontSize: 72,
            fontWeight: 800,
            background: `linear-gradient(135deg, ${primaryColor}, #3b82f6)`,
            WebkitBackgroundClip: "text",
            WebkitTextFillColor: "transparent",
            textAlign: "center",
            filter: `drop-shadow(0 0 30px ${primaryColor}40)`,
            lineHeight: 1.2,
          }}
        >
          shouldn't be this hard
        </div>
        <div
          style={{
            fontSize: 120,
            fontWeight: 800,
            color: primaryColor,
            transform: `scale(${1 + questionMarkBounce * 0.3}) rotate(${questionMarkBounce * 10}deg)`,
            marginTop: 20,
          }}
        >
          ?
        </div>

        {/* Decorative line */}
        <div
          style={{
            width: interpolate(frame, [10, 30], [0, 200], { extrapolateRight: "clamp" }),
            height: 4,
            background: `linear-gradient(90deg, transparent, ${primaryColor}, transparent)`,
            borderRadius: 2,
            marginTop: 30,
          }}
        />
      </div>
    </AbsoluteFill>
  );
};
