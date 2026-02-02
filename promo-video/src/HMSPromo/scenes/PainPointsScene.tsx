import { AbsoluteFill, interpolate, useCurrentFrame, spring, useVideoConfig } from "remotion";

interface PainPointsSceneProps {
  primaryColor: string;
  secondaryColor: string;
}

const painPoints = [
  { icon: "📋", text: "Endless paperwork", delay: 0 },
  { icon: "💸", text: "Billing errors", delay: 20 },
  { icon: "🔍", text: "Lost records", delay: 40 },
  { icon: "⏰", text: "Long wait times", delay: 60 },
  { icon: "🏥", text: "Claim rejections", delay: 80 },
  { icon: "📊", text: "No real-time data", delay: 100 },
];

export const PainPointsScene: React.FC<PainPointsSceneProps> = ({ primaryColor, secondaryColor }) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();

  const titleOpacity = interpolate(frame, [0, 15], [0, 1], {
    extrapolateRight: "clamp",
  });

  const titleY = interpolate(frame, [0, 15], [-30, 0], {
    extrapolateRight: "clamp",
  });

  const fadeOut = interpolate(frame, [160, 180], [1, 0], {
    extrapolateLeft: "clamp",
    extrapolateRight: "clamp",
  });

  // Pulsing red glow
  const glowPulse = 0.4 + Math.sin(frame * 0.08) * 0.2;

  return (
    <AbsoluteFill
      style={{
        background: "linear-gradient(180deg, #0a0f1a 0%, #1a1520 50%, #0f0a14 100%)",
        padding: "80px 50px",
        opacity: fadeOut,
        overflow: "hidden",
      }}
    >
      {/* Red warning glow */}
      <div
        style={{
          position: "absolute",
          top: -100,
          left: "50%",
          transform: "translateX(-50%)",
          width: 600,
          height: 400,
          background: `radial-gradient(ellipse, rgba(239, 68, 68, ${glowPulse}) 0%, transparent 70%)`,
          filter: "blur(80px)",
        }}
      />

      {/* Title */}
      <div
        style={{
          textAlign: "center",
          marginBottom: 60,
          opacity: titleOpacity,
          transform: `translateY(${titleY}px)`,
        }}
      >
        <div
          style={{
            fontSize: 28,
            fontWeight: 600,
            color: "#ef4444",
            textTransform: "uppercase",
            letterSpacing: 6,
            marginBottom: 20,
          }}
        >
          The Problem
        </div>
        <div
          style={{
            fontSize: 64,
            fontWeight: 800,
            color: "white",
            textShadow: "0 0 40px rgba(239, 68, 68, 0.3)",
          }}
        >
          Sound <span style={{ color: "#ef4444" }}>familiar</span>?
        </div>
      </div>

      {/* Pain points - vertical list for mobile */}
      <div
        style={{
          display: "flex",
          flexDirection: "column",
          gap: 24,
          maxWidth: 900,
          margin: "0 auto",
        }}
      >
        {painPoints.map((point, index) => {
          const itemProgress = spring({
            frame: frame - point.delay - 10,
            fps,
            config: { damping: 12, stiffness: 80 },
          });

          const shake = Math.sin((frame - point.delay) * 0.4) * 3;
          const iconBounce = 1 + Math.sin((frame - point.delay) * 0.15) * 0.08;

          return (
            <div
              key={index}
              style={{
                background: "linear-gradient(135deg, rgba(239, 68, 68, 0.12) 0%, rgba(239, 68, 68, 0.04) 100%)",
                border: "2px solid rgba(239, 68, 68, 0.3)",
                borderRadius: 24,
                padding: "32px 40px",
                display: "flex",
                alignItems: "center",
                gap: 30,
                opacity: itemProgress,
                transform: `scale(${itemProgress}) translateX(${shake}px)`,
                boxShadow: `0 8px 40px rgba(239, 68, 68, 0.15)`,
              }}
            >
              <div
                style={{
                  fontSize: 64,
                  transform: `scale(${iconBounce})`,
                  filter: "drop-shadow(0 4px 8px rgba(0,0,0,0.3))",
                }}
              >
                {point.icon}
              </div>
              <div
                style={{
                  fontSize: 42,
                  fontWeight: 700,
                  color: "white",
                }}
              >
                {point.text}
              </div>
            </div>
          );
        })}
      </div>
    </AbsoluteFill>
  );
};
