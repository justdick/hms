import { AbsoluteFill, interpolate, useCurrentFrame, spring, useVideoConfig } from "remotion";

interface SolutionRevealProps {
  primaryColor: string;
  secondaryColor: string;
  tagline: string;
}

export const SolutionReveal: React.FC<SolutionRevealProps> = ({ 
  primaryColor, 
  secondaryColor,
  tagline 
}) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();

  const logoScale = spring({
    frame,
    fps,
    config: { damping: 12, stiffness: 100 },
  });

  const glowIntensity = interpolate(frame, [20, 40], [0, 1], {
    extrapolateRight: "clamp",
  });

  const taglineOpacity = interpolate(frame, [40, 60], [0, 1], {
    extrapolateRight: "clamp",
  });

  const taglineY = interpolate(frame, [40, 60], [40, 0], {
    extrapolateRight: "clamp",
  });

  const fadeOut = interpolate(frame, [100, 120], [1, 0], {
    extrapolateLeft: "clamp",
    extrapolateRight: "clamp",
  });

  // Animated rings
  const ring1Scale = interpolate(frame, [0, 60], [0.5, 2], { extrapolateRight: "clamp" });
  const ring2Scale = interpolate(frame, [10, 70], [0.5, 2.5], { extrapolateRight: "clamp" });
  const ring3Scale = interpolate(frame, [20, 80], [0.5, 3], { extrapolateRight: "clamp" });
  const ringOpacity = interpolate(frame, [0, 30, 60], [0, 0.4, 0], { extrapolateRight: "clamp" });

  // Particle burst
  const particles = Array.from({ length: 20 }, (_, i) => ({
    angle: (i / 20) * Math.PI * 2,
    distance: 200 + (i % 3) * 80,
    size: 6 + (i % 4) * 2,
    delay: i * 2,
  }));

  return (
    <AbsoluteFill
      style={{
        background: `linear-gradient(180deg, #0a0f1a 0%, ${primaryColor}15 50%, #0a0f1a 100%)`,
        justifyContent: "center",
        alignItems: "center",
        opacity: fadeOut,
        overflow: "hidden",
      }}
    >
      {/* Animated expanding rings */}
      {[ring1Scale, ring2Scale, ring3Scale].map((scale, i) => (
        <div
          key={i}
          style={{
            position: "absolute",
            width: 200,
            height: 200,
            border: `3px solid ${primaryColor}`,
            borderRadius: "50%",
            transform: `scale(${scale})`,
            opacity: ringOpacity,
          }}
        />
      ))}

      {/* Particle burst */}
      {particles.map((particle, i) => {
        const particleProgress = spring({
          frame: frame - particle.delay,
          fps,
          config: { damping: 20, stiffness: 60 },
        });
        const x = Math.cos(particle.angle) * particle.distance * particleProgress;
        const y = Math.sin(particle.angle) * particle.distance * particleProgress;
        const particleOpacity = interpolate(
          frame - particle.delay,
          [0, 20, 50],
          [0, 1, 0],
          { extrapolateLeft: "clamp", extrapolateRight: "clamp" }
        );

        return (
          <div
            key={i}
            style={{
              position: "absolute",
              width: particle.size,
              height: particle.size,
              background: i % 2 === 0 ? primaryColor : secondaryColor,
              borderRadius: "50%",
              transform: `translate(${x}px, ${y}px)`,
              opacity: particleOpacity,
              boxShadow: `0 0 ${particle.size * 3}px ${i % 2 === 0 ? primaryColor : secondaryColor}`,
            }}
          />
        );
      })}

      {/* Background glow */}
      <div
        style={{
          position: "absolute",
          width: 700,
          height: 700,
          background: `radial-gradient(circle, ${primaryColor}40 0%, transparent 70%)`,
          borderRadius: "50%",
          filter: "blur(80px)",
          opacity: glowIntensity,
        }}
      />

      <div
        style={{
          display: "flex",
          flexDirection: "column",
          alignItems: "center",
          gap: 50,
          zIndex: 10,
          padding: "0 60px",
        }}
      >
        <div
          style={{
            transform: `scale(${logoScale})`,
            display: "flex",
            flexDirection: "column",
            alignItems: "center",
            gap: 30,
          }}
        >
          {/* Logo with enhanced glow */}
          <div
            style={{
              width: 180,
              height: 180,
              background: `linear-gradient(135deg, ${primaryColor}, ${secondaryColor})`,
              borderRadius: 44,
              display: "flex",
              justifyContent: "center",
              alignItems: "center",
              boxShadow: `
                0 0 ${100 * glowIntensity}px ${primaryColor}80,
                0 20px 60px rgba(0, 0, 0, 0.5),
                inset 0 2px 0 rgba(255, 255, 255, 0.2)
              `,
              position: "relative",
            }}
          >
            {/* Inner shine */}
            <div
              style={{
                position: "absolute",
                top: 10,
                left: 10,
                right: 10,
                height: 50,
                background: "linear-gradient(180deg, rgba(255,255,255,0.2) 0%, transparent 100%)",
                borderRadius: "34px 34px 50% 50%",
              }}
            />
            <svg width="100" height="100" viewBox="0 0 24 24" fill="none">
              <path
                d="M19 3H5C3.89 3 3 3.89 3 5V19C3 20.11 3.89 21 5 21H19C20.11 21 21 20.11 21 19V5C21 3.89 20.11 3 19 3ZM18 14H14V18H10V14H6V10H10V6H14V10H18V14Z"
                fill="white"
              />
            </svg>
          </div>

          <div
            style={{
              display: "flex",
              flexDirection: "column",
              alignItems: "center",
            }}
          >
            <div
              style={{
                fontSize: 120,
                fontWeight: 900,
                color: "white",
                letterSpacing: -4,
                textShadow: `0 0 60px ${primaryColor}60`,
                lineHeight: 1,
              }}
            >
              Healix
            </div>
            <div
              style={{
                fontSize: 56,
                fontWeight: 700,
                color: primaryColor,
                letterSpacing: 12,
                marginTop: 8,
              }}
            >
              HMS
            </div>
          </div>
        </div>

        {/* Tagline with gradient underline */}
        <div
          style={{
            display: "flex",
            flexDirection: "column",
            alignItems: "center",
            gap: 24,
            opacity: taglineOpacity,
            transform: `translateY(${taglineY}px)`,
          }}
        >
          <div
            style={{
              fontSize: 44,
              fontWeight: 600,
              background: `linear-gradient(90deg, ${primaryColor}, #3b82f6)`,
              WebkitBackgroundClip: "text",
              WebkitTextFillColor: "transparent",
              letterSpacing: 2,
              textAlign: "center",
            }}
          >
            {tagline}
          </div>
          <div
            style={{
              width: 300,
              height: 4,
              background: `linear-gradient(90deg, transparent, ${primaryColor}, transparent)`,
              borderRadius: 2,
            }}
          />
        </div>
      </div>
    </AbsoluteFill>
  );
};
