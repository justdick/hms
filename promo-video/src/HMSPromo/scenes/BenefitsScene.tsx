import { AbsoluteFill, interpolate, useCurrentFrame, spring, useVideoConfig } from "remotion";

interface BenefitsSceneProps {
  primaryColor: string;
  secondaryColor: string;
}

const benefits = [
  { value: "1000+", label: "Patients Managed", delay: 0 },
  { value: "100%", label: "Revenue Tracked", delay: 20 },
  { value: "500+", label: "Drugs Tracked", delay: 40 },
  { value: "24/7", label: "Always Available", delay: 60 },
];

export const BenefitsScene: React.FC<BenefitsSceneProps> = ({ primaryColor, secondaryColor }) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();

  const titleOpacity = interpolate(frame, [0, 20], [0, 1], {
    extrapolateRight: "clamp",
  });

  const titleY = interpolate(frame, [0, 20], [-30, 0], {
    extrapolateRight: "clamp",
  });

  const fadeOut = interpolate(frame, [160, 180], [1, 0], {
    extrapolateLeft: "clamp",
    extrapolateRight: "clamp",
  });

  // Animated gradient position
  const gradientY = 30 + Math.sin(frame * 0.03) * 20;

  return (
    <AbsoluteFill
      style={{
        background: `
          radial-gradient(ellipse at 50% ${gradientY}%, ${primaryColor}25 0%, transparent 50%),
          radial-gradient(ellipse at 50% 80%, ${secondaryColor}15 0%, transparent 40%),
          linear-gradient(180deg, #0a0f1a 0%, #111827 50%, #0a0f1a 100%)
        `,
        justifyContent: "center",
        alignItems: "center",
        padding: "80px 50px",
        opacity: fadeOut,
        overflow: "hidden",
      }}
    >
      {/* Title section */}
      <div
        style={{
          textAlign: "center",
          marginBottom: 80,
          opacity: titleOpacity,
          transform: `translateY(${titleY}px)`,
        }}
      >
        <div
          style={{
            fontSize: 28,
            fontWeight: 600,
            color: primaryColor,
            textTransform: "uppercase",
            letterSpacing: 6,
            marginBottom: 24,
          }}
        >
          Proven Results
        </div>
        <div
          style={{
            fontSize: 56,
            fontWeight: 800,
            color: "white",
            textShadow: "0 4px 30px rgba(0,0,0,0.3)",
            lineHeight: 1.2,
          }}
        >
          Built for{" "}
          <span style={{ 
            background: `linear-gradient(90deg, ${primaryColor}, ${secondaryColor})`,
            WebkitBackgroundClip: "text",
            WebkitTextFillColor: "transparent",
          }}>
            Your Hospital
          </span>
        </div>
      </div>

      {/* Benefits - 2x2 grid for vertical */}
      <div
        style={{
          display: "grid",
          gridTemplateColumns: "repeat(2, 1fr)",
          gap: 30,
          maxWidth: 900,
        }}
      >
        {benefits.map((benefit, index) => {
          const cardProgress = spring({
            frame: frame - benefit.delay - 15,
            fps,
            config: { damping: 14, stiffness: 100 },
          });

          const glowPulse = 0.4 + Math.sin((frame - benefit.delay) * 0.1) * 0.2;

          return (
            <div
              key={index}
              style={{
                background: "linear-gradient(135deg, rgba(255, 255, 255, 0.08) 0%, rgba(255, 255, 255, 0.02) 100%)",
                border: `2px solid ${primaryColor}30`,
                borderRadius: 28,
                padding: "50px 40px",
                display: "flex",
                flexDirection: "column",
                alignItems: "center",
                gap: 16,
                opacity: cardProgress,
                transform: `scale(${cardProgress})`,
                boxShadow: `0 0 60px ${primaryColor}${Math.round(glowPulse * 40).toString(16).padStart(2, '0')}`,
              }}
            >
              {/* Value */}
              <div
                style={{
                  fontSize: 72,
                  fontWeight: 900,
                  background: `linear-gradient(135deg, white, #e2e8f0)`,
                  WebkitBackgroundClip: "text",
                  WebkitTextFillColor: "transparent",
                  lineHeight: 1,
                }}
              >
                {benefit.value}
              </div>

              {/* Label */}
              <div
                style={{
                  fontSize: 28,
                  fontWeight: 600,
                  color: "#e2e8f0",
                  textAlign: "center",
                }}
              >
                {benefit.label}
              </div>
            </div>
          );
        })}
      </div>
    </AbsoluteFill>
  );
};
