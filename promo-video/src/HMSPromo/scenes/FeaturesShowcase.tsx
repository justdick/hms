import { AbsoluteFill, interpolate, useCurrentFrame, spring, useVideoConfig, Sequence } from "remotion";

interface FeaturesShowcaseProps {
  primaryColor: string;
  secondaryColor: string;
}

const features = [
  {
    title: "Patient Registration",
    description: "Quick check-in with auto-generated patient IDs",
    icon: "👤",
    color: "#3b82f6",
  },
  {
    title: "Doctor Consultations",
    description: "SOAP notes, diagnoses, and prescriptions in one place",
    icon: "🩺",
    color: "#10b981",
  },
  {
    title: "Pharmacy Management",
    description: "FIFO dispensing with batch tracking & expiry alerts",
    icon: "💊",
    color: "#8b5cf6",
  },
  {
    title: "Laboratory Integration",
    description: "Order tests, track samples, enter results digitally",
    icon: "🔬",
    color: "#f59e0b",
  },
  {
    title: "Ward & Admissions",
    description: "Bed management, ward rounds, nursing notes",
    icon: "🏥",
    color: "#ec4899",
  },
  {
    title: "Insurance Claims",
    description: "Automated claims, vetting workflow, batch submissions",
    icon: "📋",
    color: "#06b6d4",
  },
];

const FeatureCard: React.FC<{
  feature: typeof features[0];
  index: number;
  primaryColor: string;
}> = ({ feature, index, primaryColor }) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();

  const cardProgress = spring({
    frame,
    fps,
    config: { damping: 15, stiffness: 80 },
  });

  const iconBounce = spring({
    frame: frame - 15,
    fps,
    config: { damping: 8, stiffness: 150 },
  });

  return (
    <AbsoluteFill
      style={{
        justifyContent: "center",
        alignItems: "center",
        background: "linear-gradient(135deg, #0f172a 0%, #1e293b 100%)",
      }}
    >
      <div
        style={{
          display: "flex",
          gap: 80,
          alignItems: "center",
          maxWidth: 1400,
          opacity: cardProgress,
          transform: `translateX(${(1 - cardProgress) * 100}px)`,
        }}
      >
        {/* Icon Side */}
        <div
          style={{
            width: 300,
            height: 300,
            background: `linear-gradient(135deg, ${feature.color}33, ${feature.color}11)`,
            border: `3px solid ${feature.color}`,
            borderRadius: 40,
            display: "flex",
            justifyContent: "center",
            alignItems: "center",
            transform: `scale(${iconBounce})`,
            boxShadow: `0 0 60px ${feature.color}44`,
          }}
        >
          <span style={{ fontSize: 140 }}>{feature.icon}</span>
        </div>

        {/* Text Side */}
        <div style={{ flex: 1 }}>
          <div
            style={{
              fontSize: 20,
              fontWeight: 600,
              color: feature.color,
              marginBottom: 16,
              textTransform: "uppercase",
              letterSpacing: 3,
            }}
          >
            Feature {index + 1}
          </div>
          <div
            style={{
              fontSize: 64,
              fontWeight: 700,
              color: "white",
              marginBottom: 24,
              lineHeight: 1.1,
            }}
          >
            {feature.title}
          </div>
          <div
            style={{
              fontSize: 32,
              color: "#94a3b8",
              lineHeight: 1.4,
            }}
          >
            {feature.description}
          </div>
        </div>
      </div>

      {/* Progress indicator */}
      <div
        style={{
          position: "absolute",
          bottom: 60,
          display: "flex",
          gap: 12,
        }}
      >
        {features.map((_, i) => (
          <div
            key={i}
            style={{
              width: i === index ? 40 : 12,
              height: 12,
              borderRadius: 6,
              background: i === index ? primaryColor : "#475569",
              transition: "all 0.3s",
            }}
          />
        ))}
      </div>
    </AbsoluteFill>
  );
};

export const FeaturesShowcase: React.FC<FeaturesShowcaseProps> = ({ primaryColor, secondaryColor }) => {
  const FEATURE_DURATION = 60; // 2 seconds per feature

  return (
    <AbsoluteFill>
      {features.map((feature, index) => (
        <Sequence key={index} from={index * FEATURE_DURATION} durationInFrames={FEATURE_DURATION}>
          <FeatureCard feature={feature} index={index} primaryColor={primaryColor} />
        </Sequence>
      ))}
    </AbsoluteFill>
  );
};
