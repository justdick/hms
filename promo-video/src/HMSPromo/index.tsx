import { AbsoluteFill, Sequence, Audio, staticFile } from "remotion";
import { z } from "zod";
import { zColor } from "@remotion/zod-types";
import { IntroScene } from "./scenes/IntroScene";
import { PainPointsScene } from "./scenes/PainPointsScene";
import { SolutionReveal } from "./scenes/SolutionReveal";
import { AppShowcase } from "./scenes/AppShowcase";
import { BenefitsScene } from "./scenes/BenefitsScene";
import { CallToAction } from "./scenes/CallToAction";

export const hmsPromoSchema = z.object({
  primaryColor: zColor(),
  secondaryColor: zColor(),
  hospitalName: z.string(),
  tagline: z.string(),
  contactPhone: z.string(),
});

export const HMSPromo: React.FC<z.infer<typeof hmsPromoSchema>> = ({
  primaryColor,
  secondaryColor,
  hospitalName,
  tagline,
  contactPhone,
}) => {
  // Scene timings (in frames at 30fps)
  const INTRO_START = 0;
  const INTRO_DURATION = 120; // 4 seconds
  
  const PAIN_START = 120;
  const PAIN_DURATION = 180; // 6 seconds
  
  const SOLUTION_START = 300;
  const SOLUTION_DURATION = 120; // 4 seconds
  
  const APP_SHOWCASE_START = 420;
  const APP_SHOWCASE_DURATION = 800; // ~27 seconds (8 screens x 100 frames)
  
  const BENEFITS_START = 1220;
  const BENEFITS_DURATION = 180; // 6 seconds
  
  const CTA_START = 1400;
  const CTA_DURATION = 180; // 6 seconds

  return (
    <AbsoluteFill style={{ backgroundColor: "#0f172a" }}>
      {/* Scene 1: Hook/Intro */}
      <Sequence from={INTRO_START} durationInFrames={INTRO_DURATION}>
        <IntroScene primaryColor={primaryColor} />
      </Sequence>

      {/* Scene 2: Pain Points */}
      <Sequence from={PAIN_START} durationInFrames={PAIN_DURATION}>
        <PainPointsScene primaryColor={primaryColor} secondaryColor={secondaryColor} />
      </Sequence>

      {/* Scene 3: Solution Reveal */}
      <Sequence from={SOLUTION_START} durationInFrames={SOLUTION_DURATION}>
        <SolutionReveal 
          primaryColor={primaryColor} 
          secondaryColor={secondaryColor}
          tagline={tagline}
        />
      </Sequence>

      {/* Scene 4: App Showcase with real screenshots */}
      <Sequence from={APP_SHOWCASE_START} durationInFrames={APP_SHOWCASE_DURATION}>
        <AppShowcase primaryColor={primaryColor} secondaryColor={secondaryColor} />
      </Sequence>

      {/* Scene 5: Benefits */}
      <Sequence from={BENEFITS_START} durationInFrames={BENEFITS_DURATION}>
        <BenefitsScene primaryColor={primaryColor} secondaryColor={secondaryColor} />
      </Sequence>

      {/* Scene 6: Call to Action */}
      <Sequence from={CTA_START} durationInFrames={CTA_DURATION}>
        <CallToAction 
          primaryColor={primaryColor}
          secondaryColor={secondaryColor}
          contactPhone={contactPhone}
        />
      </Sequence>
    </AbsoluteFill>
  );
};
